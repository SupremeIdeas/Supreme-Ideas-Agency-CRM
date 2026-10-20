import AVFoundation
import CallKit
import Foundation
import PushKit
import TwilioVoice

extension Notification.Name { static let naaraCallsStateChanged = Notification.Name("naaraCallsStateChanged") }

enum CallsState: String { case unpaired, registering, ready, noPushCredential, pairAgain, moduleOff, error }

/// PushKit VoIP push -> CallKit -> Twilio, following Twilio's Swift quickstart. Every VoIP push MUST be reported to CallKit at once or iOS stops delivering pushes to this app.
final class CallManager: NSObject {
    static let shared = CallManager()

    let api = SoftphoneApi(baseURL: (Bundle.main.object(forInfoDictionaryKey: "NaaraBaseURL") as? String) ?? "")
    private(set) var state: CallsState = .unpaired { didSet { NotificationCenter.default.post(name: .naaraCallsStateChanged, object: nil) } }

    private let registry = PKPushRegistry(queue: .main)
    private let provider: CXProvider
    private let controller = CXCallController()
    private let audioDevice = DefaultAudioDevice()
    private var pushToken: Data?
    private var invites: [UUID: CallInvite] = [:]
    private var calls: [UUID: Call] = [:]

    private override init() {
        let cfg = CXProviderConfiguration()
        cfg.maximumCallGroups = 1
        cfg.maximumCallsPerCallGroup = 1
        cfg.supportsVideo = false
        cfg.supportedHandleTypes = [.generic, .phoneNumber]
        provider = CXProvider(configuration: cfg)
        super.init()
        provider.setDelegate(self, queue: nil)
        TwilioVoiceSDK.audioDevice = audioDevice
        registry.delegate = self
        registry.desiredPushTypes = [.voIP]
    }

    /// After pairing, on every launch and whenever PushKit issues a new token.
    func register() {
        guard api.isPaired else { state = .unpaired; return }
        guard let pushToken = pushToken else { state = .registering; return }
        state = .registering
        Task {
            do {
                let t = try await api.token()
                guard t.push else { state = .noPushCredential; return }
                TwilioVoiceSDK.register(accessToken: t.accessToken, deviceToken: pushToken) { [weak self] error in
                    DispatchQueue.main.async { self?.state = error == nil ? .ready : .error }
                }
            } catch ApiFailure.moduleOff { state = .moduleOff } catch ApiFailure.pairAgain { state = .pairAgain } catch { state = .error }
        }
    }

    /// Logout in the web view: revoke on the server first, then unregister from Twilio.
    func unpair() {
        Task {
            await api.unpair()
            state = .unpaired
        }
    }
}

extension CallManager: PKPushRegistryDelegate, NotificationDelegate {
    func pushRegistry(_ registry: PKPushRegistry, didUpdate pushCredentials: PKPushCredentials, for type: PKPushType) {
        pushToken = pushCredentials.token
        register()
    }

    func pushRegistry(_ registry: PKPushRegistry, didInvalidatePushTokenFor type: PKPushType) { pushToken = nil }

    func pushRegistry(_ registry: PKPushRegistry, didReceiveIncomingPushWith payload: PKPushPayload, for type: PKPushType, completion: @escaping () -> Void) {
        // callInviteReceived runs synchronously from here and reports to CallKit before we return.
        TwilioVoiceSDK.handleNotification(payload.dictionaryPayload, delegate: self, delegateQueue: nil)
        completion()
    }

    func callInviteReceived(callInvite: CallInvite) {
        let who = callInvite.from ?? "Naara call"
        invites[callInvite.uuid] = callInvite
        let update = CXCallUpdate()
        update.remoteHandle = CXHandle(type: .generic, value: who)
        update.localizedCallerName = who
        update.hasVideo = false
        provider.reportNewIncomingCall(with: callInvite.uuid, update: update) { [weak self] error in
            if error != nil { callInvite.reject(); self?.invites[callInvite.uuid] = nil }
        }
    }

    func cancelledCallInviteReceived(cancelledCallInvite: CancelledCallInvite, error: Error) {
        // answered elsewhere (another phone or the browser dialer) or the caller hung up
        for (uuid, invite) in invites where invite.callSid == cancelledCallInvite.callSid {
            provider.reportCall(with: uuid, endedAt: Date(), reason: .answeredElsewhere)
            invites[uuid] = nil
        }
    }
}

extension CallManager: CXProviderDelegate, CallDelegate {
    func providerDidReset(_ provider: CXProvider) {
        audioDevice.isEnabled = false
        calls.values.forEach { $0.disconnect() }
        calls.removeAll()
        invites.removeAll()
    }

    func provider(_ provider: CXProvider, perform action: CXAnswerCallAction) {
        guard let invite = invites[action.callUUID] else { return action.fail() }
        let options = AcceptOptions(callInvite: invite) { $0.uuid = invite.uuid }
        let call = invite.accept(options: options, delegate: self)
        calls[action.callUUID] = call
        invites[action.callUUID] = nil
        action.fulfill()
    }

    func provider(_ provider: CXProvider, perform action: CXEndCallAction) {
        if let invite = invites[action.callUUID] { invite.reject(); invites[action.callUUID] = nil }
        calls[action.callUUID]?.disconnect()
        action.fulfill()
    }

    func provider(_ provider: CXProvider, perform action: CXSetMutedCallAction) {
        calls[action.callUUID]?.isMuted = action.isMuted
        action.fulfill()
    }

    func provider(_ provider: CXProvider, didActivate audioSession: AVAudioSession) { audioDevice.isEnabled = true }

    func provider(_ provider: CXProvider, didDeactivate audioSession: AVAudioSession) { audioDevice.isEnabled = false }

    func callDidConnect(call: Call) {}

    func callDidFailToConnect(call: Call, error: Error) { ended(call, failed: true) }

    func callDidDisconnect(call: Call, error: Error?) { ended(call, failed: error != nil) }

    private func ended(_ call: Call, failed: Bool) {
        guard let uuid = call.uuid else { return }
        provider.reportCall(with: uuid, endedAt: Date(), reason: failed ? .failed : .remoteEnded)
        calls[uuid] = nil
    }
}

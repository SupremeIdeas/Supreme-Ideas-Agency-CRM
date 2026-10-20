import Foundation

enum ApiFailure: Error, Equatable {
    case moduleOff          // 404: the Native softphone module is off on the server
    case pairAgain          // 401/403: token gone, revoked, or no longer a workspace member
    case throttled          // 429
    case other(Int)
}

struct TokenResult { let accessToken: String; let identity: String; let ttl: Int; let push: Bool }

/// The phone's only door to the server: /api/softphone/{token,status,unpair} with the one-purpose pairing token.
final class SoftphoneApi {
    static let pairingKey = "naara.softphone.pairing"
    static let deviceKey = "naara.softphone.device"

    private let base: String
    private let store: KeyValueStore
    private let session: URLSession

    init(baseURL: String, store: KeyValueStore = KeychainStore(), session: URLSession = .shared) {
        self.base = baseURL.trimmingCharacters(in: .whitespaces).replacingOccurrences(of: "/+$", with: "", options: .regularExpression)
        self.store = store
        self.session = session
    }

    var isPaired: Bool { !(store.get(Self.pairingKey) ?? "").isEmpty }

    var deviceId: String {
        if let v = store.get(Self.deviceKey), Self.validDeviceId(v) { return v }
        let v = "ios-" + UUID().uuidString.replacingOccurrences(of: "-", with: "").lowercased()
        store.put(Self.deviceKey, v)
        return v
    }

    /// The server accepts 8 to 64 characters of A-Z a-z 0-9 . _ -
    static func validDeviceId(_ id: String) -> Bool { id.range(of: "^[A-Za-z0-9._-]{8,64}$", options: .regularExpression) != nil }

    func savePairingToken(_ token: String) { if token.count >= 20 && token.count <= 200 { store.put(Self.pairingKey, token) } }

    func forgetPairing() { store.remove(Self.pairingKey) }

    func token() async throws -> TokenResult {
        let j = try await post("token")
        guard let jwt = j["token"] as? String, !jwt.isEmpty else { throw ApiFailure.other(502) }
        return TokenResult(accessToken: jwt, identity: j["identity"] as? String ?? "", ttl: j["ttl"] as? Int ?? 3600, push: j["push"] as? Bool ?? false)
    }

    /// Tells the server to forget this phone and revoke the token, then forgets it here whatever the answer.
    func unpair() async {
        _ = try? await post("unpair")
        forgetPairing()
    }

    private func post(_ path: String) async throws -> [String: Any] {
        guard let pairing = store.get(Self.pairingKey), !pairing.isEmpty, let url = URL(string: base + "/api/softphone/" + path) else { throw ApiFailure.pairAgain }
        var req = URLRequest(url: url, timeoutInterval: 15)
        req.httpMethod = "POST"
        req.setValue("Bearer " + pairing, forHTTPHeaderField: "Authorization")
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        req.setValue("application/json", forHTTPHeaderField: "Content-Type")
        req.httpBody = try JSONSerialization.data(withJSONObject: ["device_id": deviceId, "platform": "ios"])
        let (data, resp) = try await session.data(for: req)
        let code = (resp as? HTTPURLResponse)?.statusCode ?? 0
        switch code {
        case 200...299: return (try? JSONSerialization.jsonObject(with: data) as? [String: Any]) ?? [:]
        case 404: throw ApiFailure.moduleOff
        case 401, 403: throw ApiFailure.pairAgain
        case 429: throw ApiFailure.throttled
        default: throw ApiFailure.other(code)
        }
    }
}

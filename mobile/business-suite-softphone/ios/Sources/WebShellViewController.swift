import AVFoundation
import UIKit
import WebKit

/// The Naara web app in a shell, plus a one-line banner saying whether this phone will ring. Everything but calling stays on the web.
final class WebShellViewController: UIViewController, WKScriptMessageHandler, WKNavigationDelegate {
    private let banner = UILabel()
    private var web: WKWebView!
    private let base = (Bundle.main.object(forInfoDictionaryKey: "NaaraBaseURL") as? String) ?? ""

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = UIColor(red: 0.05, green: 0.11, blue: 0.16, alpha: 1)
        let cfg = WKWebViewConfiguration()
        let api = CallManager.shared.api
        let info = Bundle.main.infoDictionary
        cfg.userContentController.add(self, name: "naara")
        cfg.userContentController.addUserScript(WKUserScript(
            source: PairScript.shim(deviceId: api.deviceId, appVersion: info?["CFBundleShortVersionString"] as? String ?? "", osVersion: UIDevice.current.systemVersion, paired: api.isPaired) ,
            injectionTime: .atDocumentStart, forMainFrameOnly: true))
        cfg.userContentController.addUserScript(WKUserScript(source: PairScript.body, injectionTime: .atDocumentEnd, forMainFrameOnly: true))
        web = WKWebView(frame: .zero, configuration: cfg)
        web.navigationDelegate = self
        banner.numberOfLines = 0
        banner.font = .systemFont(ofSize: 13)
        banner.textColor = .white
        banner.layoutMargins = UIEdgeInsets(top: 10, left: 16, bottom: 10, right: 16)
        let stack = UIStackView(arrangedSubviews: [banner, web])
        stack.axis = .vertical
        stack.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(stack)
        NSLayoutConstraint.activate([stack.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor), stack.bottomAnchor.constraint(equalTo: view.bottomAnchor),
                                     stack.leadingAnchor.constraint(equalTo: view.leadingAnchor), stack.trailingAnchor.constraint(equalTo: view.trailingAnchor)])
        NotificationCenter.default.addObserver(self, selector: #selector(render), name: .naaraCallsStateChanged, object: nil)
        if let url = URL(string: base) { web.load(URLRequest(url: url)) }
        render()
        AVAudioSession.sharedInstance().requestRecordPermission { _ in }
    }

    override func viewDidAppear(_ animated: Bool) {
        super.viewDidAppear(animated)
        CallManager.shared.register()   // refreshes the push registration on every launch
    }

    @objc private func render() {
        let (text, ok): (String, Bool) = {
            switch CallManager.shared.state {
            case .ready: return ("Calls are on. This phone rings for your business numbers, even when the app is closed.", true)
            case .registering: return ("Setting up calls on this phone", false)
            case .noPushCredential: return ("Calls are not set up on the server yet, so this phone will not ring. Ask your admin to add the iOS push credential.", false)
            case .pairAgain: return ("Sign out and back in to reconnect calls", false)
            case .moduleOff: return ("Phone calling is switched off for this workspace", false)
            case .error: return ("Could not set up calls. Check your connection and reopen the app.", false)
            case .unpaired: return ("Sign in to turn on calls for this phone", false)
            }
        }()
        banner.text = "  " + text
        banner.backgroundColor = ok ? UIColor(red: 0.04, green: 0.43, blue: 0.43, alpha: 1) : UIColor(red: 0.54, green: 0.42, blue: 0.04, alpha: 1)
    }

    func userContentController(_ ucc: WKUserContentController, didReceive message: WKScriptMessage) {
        guard let body = message.body as? [String: Any], let host = message.frameInfo.request.url?.host, host == URL(string: base)?.host else { return }
        if body["a"] as? String == "save", let t = body["t"] as? String {
            CallManager.shared.api.savePairingToken(t)
            CallManager.shared.register()
        } else if body["a"] as? String == "unpair" {
            CallManager.shared.unpair()
        }
    }

    func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        if let url = action.request.url, action.navigationType == .linkActivated, url.host != URL(string: base)?.host {
            UIApplication.shared.open(url)   // outside links open in the browser
            return decisionHandler(.cancel)
        }
        decisionHandler(.allow)
    }
}

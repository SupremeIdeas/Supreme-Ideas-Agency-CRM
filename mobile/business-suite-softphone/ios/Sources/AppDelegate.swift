import UIKit

@main
final class AppDelegate: UIResponder, UIApplicationDelegate {
    var window: UIWindow?

    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
        _ = CallManager.shared   // PushKit must be registered at launch so a VoIP push can wake the app
        window = UIWindow(frame: UIScreen.main.bounds)
        window?.rootViewController = WebShellViewController()
        window?.makeKeyAndVisible()
        return true
    }
}

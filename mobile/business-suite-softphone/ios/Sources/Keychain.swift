import Foundation
import Security

/// The pairing token and device id live in the Keychain, never in web storage.
enum Keychain {
    static func set(_ value: String, for key: String) {
        let q: [String: Any] = [kSecClass as String: kSecClassGenericPassword, kSecAttrAccount as String: key]
        SecItemDelete(q as CFDictionary)
        var add = q
        add[kSecValueData as String] = Data(value.utf8)
        add[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlock   // must be readable when a VoIP push wakes the app on a locked phone
        SecItemAdd(add as CFDictionary, nil)
    }

    static func get(_ key: String) -> String? {
        let q: [String: Any] = [kSecClass as String: kSecClassGenericPassword, kSecAttrAccount as String: key, kSecReturnData as String: true]
        var out: AnyObject?
        guard SecItemCopyMatching(q as CFDictionary, &out) == errSecSuccess, let d = out as? Data else { return nil }
        return String(data: d, encoding: .utf8)
    }

    static func remove(_ key: String) {
        SecItemDelete([kSecClass as String: kSecClassGenericPassword, kSecAttrAccount as String: key] as CFDictionary)
    }
}

protocol KeyValueStore {
    func get(_ key: String) -> String?
    func put(_ key: String, _ value: String)
    func remove(_ key: String)
}

struct KeychainStore: KeyValueStore {
    func get(_ key: String) -> String? { Keychain.get(key) }
    func put(_ key: String, _ value: String) { Keychain.set(value, for: key) }
    func remove(_ key: String) { Keychain.remove(key) }
}

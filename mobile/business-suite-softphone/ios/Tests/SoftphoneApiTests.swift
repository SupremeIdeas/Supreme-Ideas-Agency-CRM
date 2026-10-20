import XCTest
@testable import NaaraPhone

final class MemoryStore: KeyValueStore {
    var m: [String: String] = [:]
    func get(_ key: String) -> String? { m[key] }
    func put(_ key: String, _ value: String) { m[key] = value }
    func remove(_ key: String) { m[key] = nil }
}

final class StubProtocol: URLProtocol {
    static var status = 200
    static var body = "{}"
    static var lastRequest: URLRequest?
    override class func canInit(with request: URLRequest) -> Bool { true }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }
    override func startLoading() {
        StubProtocol.lastRequest = request
        let r = HTTPURLResponse(url: request.url!, statusCode: StubProtocol.status, httpVersion: nil, headerFields: nil)!
        client?.urlProtocol(self, didReceive: r, cacheStoragePolicy: .notAllowed)
        client?.urlProtocol(self, didLoad: Data(StubProtocol.body.utf8))
        client?.urlProtocolDidFinishLoading(self)
    }
    override func stopLoading() {}
}

final class SoftphoneApiTests: XCTestCase {
    private func api(_ store: MemoryStore = MemoryStore()) -> SoftphoneApi {
        let cfg = URLSessionConfiguration.ephemeral
        cfg.protocolClasses = [StubProtocol.self]
        let a = SoftphoneApi(baseURL: "https://x.test//", store: store, session: URLSession(configuration: cfg))
        a.savePairingToken("1|abcdefghijklmnopqrstuvwxyz0123456789")
        return a
    }

    func testTokenSendsBearerAndParses() async throws {
        StubProtocol.status = 200
        StubProtocol.body = #"{"token":"jwt","identity":"naara_user_7","ttl":3600,"push":true}"#
        let t = try await api().token()
        XCTAssertEqual(StubProtocol.lastRequest?.url?.absoluteString, "https://x.test/api/softphone/token")
        XCTAssertEqual(StubProtocol.lastRequest?.value(forHTTPHeaderField: "Authorization"), "Bearer 1|abcdefghijklmnopqrstuvwxyz0123456789")
        XCTAssertTrue(t.push)
        XCTAssertEqual(t.identity, "naara_user_7")
    }

    func testStatusCodesMapToActions() async {
        let cases: [(Int, ApiFailure)] = [(404, .moduleOff), (403, .pairAgain), (401, .pairAgain), (429, .throttled), (500, .other(500))]
        for (code, want) in cases {
            StubProtocol.status = code
            do { _ = try await api().token(); XCTFail("expected \(code)") } catch { XCTAssertEqual(error as? ApiFailure, want) }
        }
    }

    func testDeviceIdIsStableAndValid() {
        let s = MemoryStore(); let a = api(s)
        XCTAssertEqual(a.deviceId, a.deviceId)
        XCTAssertTrue(SoftphoneApi.validDeviceId(a.deviceId))
    }

    func testUnpairForgetsEvenWhenServerFails() async {
        StubProtocol.status = 500
        let a = api()
        await a.unpair()
        XCTAssertFalse(a.isPaired)
    }

    func testPairScriptNeverTouchesWebStorage() {
        XCTAssertTrue(PairScript.body.contains("/softphone/native/pair"))
        XCTAssertFalse(PairScript.body.contains("localStorage") || PairScript.body.contains("document.cookie"))
    }
}

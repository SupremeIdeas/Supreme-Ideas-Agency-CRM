<?php

namespace App\Http\Controllers\Omni;

use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use App\Services\Omni\WordPress\WebChatPluginPackager;
use App\Support\Business\WorkspaceAdminAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Owner or admin of a workspace downloads the WordPress plugin pre-set for THEIR OWN web chat (MASTER-ONLY). Another workspace's chat is a plain 404. */
class WebChatPluginController extends Controller
{
    public function __invoke(Request $request, WebChatPluginPackager $packager, int $connection): BinaryFileResponse
    {
        $group = WorkspaceAdminAccess::groupFor($request->user());
        abort_unless($group !== null, 404);
        $conn = ChannelConnection::query()->where('wallet_group_id', $group->id)->where('channel', ChannelConnection::WEBCHAT)->find($connection);
        abort_unless($conn !== null && $conn->public_id, 404);

        return response()->download($packager->build((string) $conn->public_id), 'naara-respond-chat-'.WebChatPluginPackager::version().'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}

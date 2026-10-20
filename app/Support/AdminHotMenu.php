<?php

namespace App\Support;

use App\Models\AdminSectionView;
use App\Models\KycVerification;
use App\Models\Merchant;
use App\Models\PaymentDispute;
use App\Models\PaymentRefund;
use App\Models\PayoutRequest;
use App\Models\PortInRequest;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use Illuminate\Support\Carbon;

/**
 * Content/billing/hot-menu blueprint Phase G — the admin "Hot Menu": a
 * header-triggered modal listing the 16 named admin sections the owner called
 * out, gated by the EXACT same role/permission checks as the sidebar nav
 * (`resources/views/components/layouts/admin.blade.php`) so the two never
 * drift apart.
 *
 * Badge counts mirror `NotificationCenter`'s own unread-since-last-check
 * pattern: a count is "qualifying records created after this admin's
 * last_viewed_at for that section" (epoch if never viewed), backed by the
 * `admin_section_views` table. Only sections with a real inbound work queue
 * get a counter — Blog/Coupons/Announcements/Login notices/Email broadcast/
 * Staff/Users/Maintenance are admin-authored tools, not queues, so they're
 * listed with no badge rather than a fabricated one.
 *
 * KYC's `escalation` sub-count is a deliberate placeholder: no escalation
 * concept exists on `KycVerification` yet (Tier 5 #11 adds it). It always
 * returns 0 today and the UI only renders the sub-badge when it's > 0, so
 * this lights up automatically once #11 lands real escalation data — no
 * fabricated count in the meantime.
 */
class AdminHotMenu
{
    private const EPOCH = '1970-01-01 00:00:00';

    /**
     * @return array<int, array{key:string,label:string,route:string,icon:string}>
     */
    private static function definitions(): array
    {
        return [
            ['key' => 'staff', 'label' => 'Staff', 'route' => 'admin.staff', 'icon' => 'id-card', 'gate' => 'super'],
            ['key' => 'merchants', 'label' => 'Merchants', 'route' => 'admin.merchants', 'icon' => 'id-card', 'gate' => 'privileged'],
            ['key' => 'kyc', 'label' => 'Identity (KYC)', 'route' => 'admin.kyc', 'icon' => 'shield', 'gate' => 'privileged'],
            ['key' => 'payouts', 'label' => 'Payouts', 'route' => 'admin.payouts', 'icon' => 'credit-card', 'gate' => 'privileged'],
            ['key' => 'users', 'label' => 'Users', 'route' => 'admin.users', 'icon' => 'id-card', 'gate' => 'privileged'],
            ['key' => 'refunds', 'label' => 'Refunds & Disputes', 'route' => 'admin.refunds', 'icon' => 'refresh', 'gate' => 'privileged'],
            ['key' => 'white_label', 'label' => 'White-Label Oversight', 'route' => 'admin.white-label', 'icon' => 'users', 'gate' => 'privileged'],
            ['key' => 'email_broadcast', 'label' => 'Email Broadcast', 'route' => 'admin.email-broadcast', 'icon' => 'send', 'gate' => 'privileged'],
            ['key' => 'deletions', 'label' => 'Deletions', 'route' => 'admin.deletions', 'icon' => 'trash', 'gate' => 'privileged'],
            ['key' => 'port_in', 'label' => 'Port-in Requests', 'route' => 'admin.port-in-requests', 'icon' => 'phone-forwarded', 'gate' => 'privileged'],
            ['key' => 'blog', 'label' => 'Blog', 'route' => 'admin.blog', 'icon' => 'file-text', 'gate' => 'privileged'],
            ['key' => 'maintenance', 'label' => 'Maintenance', 'route' => 'admin.maintenance', 'icon' => 'refresh', 'gate' => 'super'],
            ['key' => 'tickets', 'label' => 'Tickets', 'route' => 'admin.tickets', 'icon' => 'message-circle', 'gate' => 'tickets'],
            ['key' => 'coupons', 'label' => 'Coupons', 'route' => 'admin.coupons', 'icon' => 'gift', 'gate' => 'privileged'],
            ['key' => 'announcements', 'label' => 'Announcements', 'route' => 'admin.announcements', 'icon' => 'bell', 'gate' => 'privileged'],
            ['key' => 'login_notice', 'label' => 'Login Notice', 'route' => 'admin.notices', 'icon' => 'bell', 'gate' => 'privileged'],
        ];
    }

    private static function passesGate(User $admin, string $gate): bool
    {
        $isPrivileged = $admin->hasAnyRole(['super_admin', 'admin']);

        return match ($gate) {
            'super' => $admin->hasRole('super_admin'),
            'tickets' => $isPrivileged || $admin->can('tickets.manage'),
            default => $isPrivileged,
        };
    }

    /**
     * The sections this admin may see, each with a live badge count (null
     * when the section has no queue concept), in the owner's named order.
     *
     * @return array<int, array{key:string,label:string,route:string,icon:string,count:?int,escalation:?int}>
     */
    public static function for(User $admin): array
    {
        $viewedAt = AdminSectionView::where('user_id', $admin->id)
            ->pluck('last_viewed_at', 'section_key');

        $sections = [];
        foreach (self::definitions() as $def) {
            if (! self::passesGate($admin, $def['gate']) || ! \Illuminate\Support\Facades\Route::has($def['route'])) {
                continue;
            }

            $since = $viewedAt->get($def['key']) ?? Carbon::parse(self::EPOCH);

            $sections[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'route' => $def['route'],
                'icon' => $def['icon'],
                'count' => self::count($def['key'], $since),
                'escalation' => $def['key'] === 'kyc' ? self::kycEscalationCount($since) : null,
            ];
        }

        return $sections;
    }

    public static function totalBadge(User $admin): int
    {
        return collect(self::for($admin))->sum(fn (array $s) => ($s['count'] ?? 0) + ($s['escalation'] ?? 0));
    }

    /** Record that this admin has just opened a section — clears its badge. */
    public static function markViewed(User $admin, string $sectionKey): void
    {
        AdminSectionView::updateOrCreate(
            ['user_id' => $admin->id, 'section_key' => $sectionKey],
            ['last_viewed_at' => now()],
        );
    }

    private static function count(string $key, Carbon $since): ?int
    {
        return match ($key) {
            'merchants' => Merchant::where('status', Merchant::PENDING)->where('created_at', '>', $since)->count(),
            'kyc' => KycVerification::where('status', KycVerification::PENDING)->where('created_at', '>', $since)->count(),
            'payouts' => PayoutRequest::where('status', PayoutRequest::PENDING)->where('created_at', '>', $since)->count(),
            'refunds' => PaymentRefund::where('status', PaymentRefund::STATUS_PENDING)->where('created_at', '>', $since)->count()
                + PaymentDispute::where('status', PaymentDispute::STATUS_OPEN)->where('created_at', '>', $since)->count(),
            'white_label' => WhiteLabelInstance::whereIn('status', [WhiteLabelInstance::PENDING, WhiteLabelInstance::APPROVED_AWAITING_FUNDS])
                ->where('created_at', '>', $since)->count(),
            'deletions' => User::whereNotNull('deletion_requested_at')->whereNull('deletion_approved_at')
                ->where('deletion_requested_at', '>', $since)->count(),
            'port_in' => PortInRequest::whereNotIn('status', [PortInRequest::STATUS_COMPLETED, PortInRequest::STATUS_REJECTED])
                ->where('created_at', '>', $since)->count(),
            'tickets' => SupportConversation::where(function ($q) {
                $q->where('escalated', true)->orWhere('status', 'assigned');
            })->where('created_at', '>', $since)->count(),
            default => null, // Staff/Users/Blog/Maintenance/Coupons/Announcements/Login notice/Email
            // broadcast are admin-authored tools, not inbound queues — no badge
            // to fabricate.
        };
    }

    /** @see class docblock — always 0 until Tier 5 #11 adds real escalation data. */
    private static function kycEscalationCount(Carbon $since): int
    {
        return 0;
    }
}

<?php

namespace App\Support;

/**
 * PERMANENT MASTER-ONLY PRODUCT MODULES — NEVER SHIPPED TO A WHITE-LABEL FORK.
 *
 * Same standing rule as the License Surgery boundary (docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md §6, CLAUDE.md
 * "WHITE-LABEL SHIPPING DISCIPLINE"): some capabilities belong to the master platform for good and must never reach
 * NaaraSim-WhiteLabel or NaaraSim-White-Label-Extented — not by a package, not by a manual port, not "because it is only a
 * small piece". Do not re-litigate this with the owner; apply it.
 *
 *   Naara Widgets (merchant embed + WordPress loader; `MerchantWidget*`, tables `merchant_widget_*`, routes `merchant-widget.*`,
 *   `v1/merchant-widget/*`, `/widget/checkout/*`): the merchant distribution channel. Owner decision 2026-10-03: master-only.
 *
 *   N2N (Naara-to-Naara): in-network calling and messaging between verified Naara IDs, its funding-source choice
 *   (NaaraCredit vs Wallet), chat themes, conversation list/search, abuse reporting, system-message bubbles, and every
 *   table, route, job, view, language file and config that exists only for it. Owner decision 2026-10-03: PERMANENTLY
 *   master-only.
 *
 *   Platform Bootstrap (naara:bootstrap, credential slots, Config Doctor): install-time tooling for THIS platform's defaults; master-only until the
 *   owner picks which parts a white-label should get (the list is in docs/ADMIN-FIRST-DAY.md).
 *
 *   Master marketing (the NaaraChat landing, the White Label sales page, the expanded "What Naara Gives You" sections): the
 *   public pages that sell master-only products. Files carry `MasterMarketing`/`master-marketing` in their path.
 *   NaaraBusiness Suite (`App\\Support\\Business`, `Services\\Business`, `Livewire\\Business*`, `/business/*` routes, `business_*` tables/migrations, `layouts/business`). Owner: master-only, never a white-label.
 *   NaaraBrain (the user-facing AI assistant: `Brain*` classes, `brain_*` tables, `brain.` routes, views under a brain directory, config/brain.php, tests
 *   the Brain test folder, the `naarabrain-*` brand assets). Owner decision 2026-10-05: master-only. The shared Claude gateway (App\Services\AI\ClaudeGateway,
 *   ai_usage_log, config/ai.php) is NaaraBrain Sprint 0 and is master-only too until the owner says otherwise.
 *
 * Naming convention (so the guard below stays mechanical): everything N2N lives under a path containing `N2N`/`n2n`
 * (App\...\N2N\*, resources/views/**\/n2n/*, database/migrations/*n2n*, lang/*\/n2n.php, config/n2n.php, tests/**\/N2N*,
 * routes named `n2n.*`, tables `n2n_*`). A new master-only module is added by appending its token to TOKENS.
 *
 * Enforced three ways: PackageBuilder refuses to put these paths into a distributable package; PackagePublisher refuses to
 * publish one that has them; the forks' WhiteLabelBoundaryFitnessTest fails the build if any of it appears there.
 */
final class MasterOnlyModules
{
    /** Case-insensitive path tokens. Add a module by adding its token; never remove one. */
    public const TOKENS = ['n2n', 'brain', 'omni'];

    /**
     * Distinctive tokens matched ANYWHERE in a path (a class like EnsureMerchantWidgetKeyUsable has no separator before the token, and
     * these strings are long enough never to occur by accident). Naara Widgets is master-only.
     */
    public const SUBSTRING_TOKENS = ['providerapiwatch', 'apiversionwatch', 'provider_api_watch', 'merchantwidget', 'merchant_widget', 'merchant-widget', 'mastermarketing', 'master-marketing', 'master_marketing',
        // Platform Bootstrap tooling (install-time defaults, credential slots, preflight). Master-only by default (owner CLAUDE.md); match full path pieces, never the repo's own `bootstrap/` folder.
        'services/bootstrap/', 'support/bootstrap/', 'commands/bootstrap/', 'models/bootstrap/', 'config/bootstrap/', 'feature/bootstrap/', 'admin/bootstrap/', 'bootstrap_manifest', 'bootstrap-audit', 'pricing-defaults-report', 'naarabrain',
        // NaaraBusiness Suite (workspaces, call flows, recordings, omnichannel inbox, AI voice, integrations API). Owner: master-only, never a white-label.
        'business-suite', 'business_suite', 'businesssuite', 'app/support/business/', 'app/services/business/', 'controllers/business/', 'controllers/api/v1/business', 'app/livewire/business', 'app/livewire/admin/business',
        'views/business/', 'layouts/business', 'views/livewire/business', 'views/livewire/admin/business', 'marketing/business', 'ensurebusiness', 'commands/business', 'add_business_', 'create_business_', 'business-email-verify', 'call-flow-builder', 'callflowbuilder', 'images/business/', 'gifs/business', 'jobs/business', 'businessbrain', 'business_brain',
        // Brand-Growth (Business links, trials, Business Card, Connect journey, unified router). Owner: brand directory growth tooling and trial billing stay out of white-label forks.
        'brandlink', 'brand_link', 'brand-link', 'brandtrial', 'brand_trial', 'brand-trial', 'businesscard', 'business_card', 'business-card', 'connectionintent', 'connection_intent', 'conversationrouter', 'conversation_router', 'conversation-router', 'brandconnect', 'brand_connect', 'brand-connect', 'handbook', 'masterreviewmoment', 'master_review_moment', 'conversationevent', 'conversation_event', 'conversation-event', 'conversationtimeline', 'messagingreconcile', 'messaging-reconcile', 'brandvisibility', 'brand_visibility',
        'followreward', 'follow_reward', 'follow-reward', 'brandvisit', 'brand_visit', 'brand-visit', 'profile_visit', 'profilevisit',
        // White Label purchase funnel (instances, wizard, later payments and invoicing). Master only: it sells the licence, a fork never does.
        'wlfunnel', 'wl_funnel', 'wl-funnel', 'wlcustomorder', 'wl_custom_order', 'wl-custom-order',
        // White Label funnel documents, balance payment, manual-payment accounts and rates, rate quoting, seeds and the connect popup (found unguarded on 2026-10-12 by listing every WL file against this list).
        'wlinvoice', 'wl_invoice', 'wl-invoice', 'wlbalance', 'wl-balance', 'wlsecrets', 'wl_secrets', 'wlseedmanual', 'whitelabel/funnel/', 'whitelabel/payments/', 'manualpayment', 'manual_payment', 'wlratequoter',
        'whitelabelpayments', 'white-label-payments', 'connectprompt', 'connect-prompt', 'connect_prompt', 'wl-hub',
        // Provider login manager (where and how the owner signs in to each supplier dashboard; the owner's own emails). Master-only, never a white-label.
        'providerlogin', 'provider_login', 'provider-login',
        // Native softphone (VoIP push, device registry, mobile shell). Master-only with the rest of the Business Suite.
        'softphone'];

    /** @return list<string> the paths that belong to a master-only module */
    public static function offending(array $paths): array
    {
        $out = [];
        foreach ($paths as $path) {
            if (self::matches((string) $path)) {
                $out[] = (string) $path;
            }
        }

        return $out;
    }

    public static function matches(string $path): bool
    {
        $p = strtolower(str_replace('\\', '/', $path));
        foreach (self::SUBSTRING_TOKENS as $token) {
            if (str_contains($p, $token)) {
                return true;
            }
        }
        foreach (self::TOKENS as $token) {
            // a path SEGMENT or file-name part named for the module (n2n/, N2NController.php, 2026_..._create_n2n_x_table.php)
            if (preg_match('#(^|[/_.\-])'.preg_quote($token, '#').'([/_.\-]|[a-z]|$)#', $p) === 1) {
                return true;
            }
        }

        return false;
    }
}

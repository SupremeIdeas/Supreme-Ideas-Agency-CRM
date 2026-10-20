<?php

namespace Tests\Feature\BusinessSuite;

use App\Support\Business\BusinessNav;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The Business shell's navigation: every destination is a real route and no name or destination appears twice. */
class BusinessNavIntegrityTest extends TestCase
{
    public function test_every_business_destination_exists_and_none_is_listed_twice(): void
    {
        $labels = array_column(BusinessNav::CATALOG, 'label');
        $routes = array_column(BusinessNav::CATALOG, 'route');
        $this->assertSame($labels, array_values(array_unique($labels)), 'a label appears twice in the Business nav');
        $this->assertSame($routes, array_values(array_unique($routes)), 'a destination appears twice in the Business nav');
        foreach ($routes as $r) {
            $this->assertTrue(Route::has($r), "Business nav points at a missing route [$r]");
        }
    }

    public function test_the_admin_menu_never_lists_the_same_name_twice_in_one_render(): void
    {
        $src = (string) file_get_contents(resource_path('views/components/layouts/admin.blade.php'));
        // The only repeated labels in the source are mutually exclusive branches (privileged vs delegated staff; funnel vs legacy White Label).
        preg_match_all("/'label' => '([^']+)'/", $src, $m);
        $dupes = array_keys(array_filter(array_count_values($m[1]), fn ($n) => $n > 1));
        sort($dupes);
        $this->assertSame(['White Label', 'eSIM Control Center'], $dupes, 'a new duplicate admin menu label appeared: make it a single entry or an exclusive branch');
    }
}

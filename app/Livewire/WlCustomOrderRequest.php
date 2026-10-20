<?php

namespace App\Livewire;

use App\Models\WlCustomOrder;
use App\Services\WhiteLabel\Funnel\WlCustomOrderException;
use App\Services\WhiteLabel\Funnel\WlCustomOrders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** A buyer asks for a custom White Label build and sees their own requests (Funnel Phase 8, MASTER-ONLY). */
#[Layout('components.layouts.customer')]
class WlCustomOrderRequest extends Component
{
    public string $brand_name = '';

    public string $contact_email = '';

    public string $contact_whatsapp = '';

    public array $modules = [];

    public string $budget_band = '';

    public string $timeline = '';

    public string $brief = '';

    public string $reference_link = '';

    public ?string $error = null;

    public function mount()
    {
        abort_unless(WlCustomOrders::enabled(), 404);
        $this->contact_email = (string) Auth::user()->email;
    }

    public function submit(WlCustomOrders $orders)
    {
        $this->error = null;
        try {
            $order = $orders->request(Auth::user(), [
                'brand_name' => $this->brand_name, 'contact_email' => $this->contact_email, 'contact_whatsapp' => $this->contact_whatsapp ?: null, 'modules' => $this->modules,
                'budget_band' => $this->budget_band ?: null, 'timeline' => $this->timeline ?: null, 'brief' => $this->brief, 'reference_link' => $this->reference_link ?: null,
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        } catch (WlCustomOrderException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('wl-custom-orders.show', ['reference' => $order->reference], navigate: true);
    }

    public function render()
    {
        return view('livewire.wl-custom-order-request', [
            'orders' => WlCustomOrder::query()->where('user_id', Auth::id())->latest('id')->limit(20)->get(),
            'moduleOptions' => WlCustomOrders::MODULES, 'budgets' => WlCustomOrders::BUDGETS, 'timelines' => WlCustomOrders::TIMELINES,
        ]);
    }
}

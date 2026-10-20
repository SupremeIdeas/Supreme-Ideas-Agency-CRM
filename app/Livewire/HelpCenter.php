<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\SupportConversation;
use App\Support\SocialLinks;
use App\Support\SupportSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The Help Center hub (Naara Care overhaul): one route that works both
 * inside and outside the authenticated app (guests see it too — only the
 * actual chat thread at /support requires signing in). Shows a start-chat
 * card, recent blog posts, and the brand's social handles — the "scrollable
 * other options" the owner asked for alongside the primary chat CTA.
 */
#[Layout('components.layouts.help-center')]
class HelpCenter extends Component
{
    public string $search = '';

    public function render()
    {
        $user = Auth::user();

        $openConversation = $user
            ? SupportConversation::where('user_id', $user->id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->whereHas('messages')
                ->latest('updated_at')
                ->first()
            : null;

        $query = trim($this->search);
        $articles = Post::published()
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($q2) use ($query) {
                    $q2->where('title', 'like', "%{$query}%")
                        ->orWhere('excerpt', 'like', "%{$query}%")
                        ->orWhere('category', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('livewire.help-center', [
            'agentName' => SupportSettings::name(),
            'agentAvatar' => SupportSettings::avatar(),
            'openConversation' => $openConversation,
            'articles' => $articles,
            'socials' => SocialLinks::forFooter(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MindmeetFeedback;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMindmeetFeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $rating = $request->query('rating');

        $query = MindmeetFeedback::query()
            ->with(['user:id,name,email,image'])
            ->latest();

        if ($search !== '') {
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (is_numeric($rating)) {
            $query->where('rating', (int) $rating);
        }

        $feedback = $query->paginate(15)->withQueryString();

        $baseQuery = MindmeetFeedback::query();

        return Inertia::render('MindmeetFeedback/Index', [
            'feedback' => $feedback,
            'filters' => [
                'search' => $search,
                'rating' => $rating,
            ],
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'average_rating' => round((float) (clone $baseQuery)->avg('rating'), 2),
                'positive' => (clone $baseQuery)->where('rating', '>=', 4)->count(),
                'needs_attention' => (clone $baseQuery)->where('rating', '<=', 2)->count(),
            ],
        ]);
    }
}

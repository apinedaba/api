<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MindmeetBenefit;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class AdminMindmeetBenefitController extends Controller
{
    public function index()
    {
        return Inertia::render('MindmeetBenefits', [
            'benefits' => MindmeetBenefit::query()
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (MindmeetBenefit $benefit) => $this->serialize($benefit)),
            'categories' => MindmeetBenefit::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $this->attachUploadedImage($request, $data);

        MindmeetBenefit::create($data);

        return Redirect::route('mindmeet-benefits.index')
            ->with('success', 'Beneficio creado correctamente.');
    }

    public function update(Request $request, MindmeetBenefit $mindmeetBenefit)
    {
        $data = $this->validatedData($request);
        $this->attachUploadedImage($request, $data);

        $mindmeetBenefit->update($data);

        return Redirect::route('mindmeet-benefits.index')
            ->with('success', 'Beneficio actualizado correctamente.');
    }

    public function destroy(MindmeetBenefit $mindmeetBenefit)
    {
        $mindmeetBenefit->delete();

        return Redirect::route('mindmeet-benefits.index')
            ->with('success', 'Beneficio eliminado correctamente.');
    }

    protected function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'partner_name' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1200'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'coupon_code' => ['nullable', 'string', 'max:80'],
            'image_url' => ['nullable', 'url', 'max:1000'],
            'image' => ['nullable', 'image'],
            'redirect_url' => ['nullable', 'url', 'max:1000'],
            'contact_label' => ['nullable', 'string', 'max:80'],
            'contact_url' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    protected function attachUploadedImage(Request $request, array &$data): void
    {
        unset($data['image']);

        if (!$request->hasFile('image')) {
            return;
        }

        $result = (new UploadApi())->upload($request->file('image')->getRealPath(), [
            'folder' => 'mindmeet-benefits',
        ]);

        $data['image_url'] = $result['secure_url'] ?? $data['image_url'] ?? null;
        $data['image_public_id'] = $result['public_id'] ?? null;
    }

    protected function serialize(MindmeetBenefit $benefit): array
    {
        return [
            'id' => $benefit->id,
            'title' => $benefit->title,
            'partner_name' => $benefit->partner_name,
            'category' => $benefit->category,
            'description' => $benefit->description,
            'terms' => $benefit->terms,
            'coupon_code' => $benefit->coupon_code,
            'image_url' => $benefit->image_url,
            'redirect_url' => $benefit->redirect_url,
            'contact_label' => $benefit->contact_label,
            'contact_url' => $benefit->contact_url,
            'starts_at' => optional($benefit->starts_at)->format('Y-m-d\TH:i'),
            'ends_at' => optional($benefit->ends_at)->format('Y-m-d\TH:i'),
            'sort_order' => $benefit->sort_order,
            'is_active' => $benefit->is_active,
            'is_available' => $benefit->is_active
                && (!$benefit->starts_at || $benefit->starts_at->isPast())
                && (!$benefit->ends_at || $benefit->ends_at->isFuture()),
            'created_at' => optional($benefit->created_at)->format('d/m/Y'),
        ];
    }
}

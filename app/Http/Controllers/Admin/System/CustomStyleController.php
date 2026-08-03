<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\AppearanceSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomStyleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/System/CustomStyle', [
            'admin_settings' => AppearanceSetting::getForScope('admin'),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:admin'],
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        $scope = $validated['scope'];
        $settings = $validated['settings'];

        $colorKeys = [
            'primary_color', 'secondary_color', 'accent_color', 'bg_color',
            'surface_color', 'sidebar_bg', 'sidebar_text_color',
            'navbar_bg', 'navbar_text_color', 'text_primary_color',
            'text_secondary_color', 'link_color', 'button_color',
            'button_hover_color', 'header_background', 'footer_background',
            'bg_gradient',
        ];

        $allowedFontSizes = ['12px', '13px', '14px', '15px', '16px', '18px', '20px'];
        $allowedHeadingWeight = ['400', '500', '600', '700', '800'];
        $allowedLineHeight = ['1.25', '1.375', '1.5', '1.625', '1.75', '2'];
        $allowedLetterSpacing = ['tighter', 'tight', 'normal', 'wide', 'wider'];
        $allowedContainerWidth = ['full', '1080px', '1280px', '1536px'];
        $allowedBgSize = ['cover', 'contain', 'auto'];
        $allowedBgRepeat = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];
        $allowedBgAttachment = ['scroll', 'fixed'];
        $allowedBgPosition = ['center', 'top', 'bottom', 'left', 'right', 'top left', 'top right', 'bottom left', 'bottom right'];

        foreach ($settings as $key => $value) {
            if ($value === null || $value === '') {
                AppearanceSetting::where('scope', $scope)->where('key', $key)->delete();
                continue;
            }

            if (in_array($key, $colorKeys, true) && ! preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                continue;
            }



            if ($key === 'base_font_size' && ! in_array($value, $allowedFontSizes, true)) {
                continue;
            }

            if ($key === 'heading_weight' && ! in_array($value, $allowedHeadingWeight, true)) {
                continue;
            }

            if ($key === 'line_height' && ! in_array($value, $allowedLineHeight, true)) {
                continue;
            }

            if ($key === 'letter_spacing' && ! in_array($value, $allowedLetterSpacing, true)) {
                continue;
            }

            if ($key === 'container_width' && ! in_array($value, $allowedContainerWidth, true) && ! preg_match('/^\d+px$/', $value)) {
                continue;
            }

            if ($key === 'bg_size' && ! in_array($value, $allowedBgSize, true)) {
                continue;
            }

            if ($key === 'bg_repeat' && ! in_array($value, $allowedBgRepeat, true)) {
                continue;
            }

            if ($key === 'bg_attachment' && ! in_array($value, $allowedBgAttachment, true)) {
                continue;
            }

            if ($key === 'bg_position' && ! in_array($value, $allowedBgPosition, true)) {
                continue;
            }

            AppearanceSetting::updateOrCreate(
                ['scope' => $scope, 'key' => $key],
                ['value' => $value]
            );
        }

        return back()->with('success', translate('Appearance settings updated.'));
    }
}

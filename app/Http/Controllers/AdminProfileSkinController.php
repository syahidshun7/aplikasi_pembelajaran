<?php

namespace App\Http\Controllers;

use App\Models\ProfileSkin;
use App\Models\ShopItem;
use App\Support\Cache\CacheVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminProfileSkinController extends Controller
{
    public function index(): Response
    {
        $skins = ProfileSkin::query()
            ->with('shopItem:id,code,name,price_gold,is_active')
            ->withCount('users')
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/ProfileSkins/Index', [
            'skins' => $skins,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:500'],
            'price_gold'     => ['required', 'integer', 'min:1'],
            'renderer_type'   => ['nullable', 'string', 'in:config,vue_template,project_static'],
            'template_key'   => ['required', 'string', 'in:default,void_phantom,arcade_cabinet,asset_showcase,project_static'],
            'hero_gradient'  => ['required', 'string', 'max:300'],
            'accent_color'   => ['required', 'string', 'max:30'],
            'border_color'   => ['required', 'string', 'max:30'],
            'glow_color'     => ['required', 'string', 'max:60'],
            'stat_panel_bg'  => ['required', 'string', 'max:30'],
            'text_primary'   => ['required', 'string', 'max:30'],
            'is_active'      => ['required', 'boolean'],
            'preview_image'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'background_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'avatar_frame_image' => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'panel_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'decoration_image' => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
        ]);

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $i = 1;
        while (ProfileSkin::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        DB::transaction(function () use ($request, $validated, $slug) {
            $previewPath = null;
            if ($request->hasFile('preview_image')) {
                $previewPath = $request->file('preview_image')->store('profile-skins', 'public');
            }

            $shopItem = ShopItem::create([
                'code'         => $this->makeUniqueShopCode($validated['name']),
                'name'         => 'Skin: ' . $validated['name'],
                'description'  => $validated['description'],
                'price_gold'   => (int) $validated['price_gold'],
                'icon_path'    => $previewPath,
                'is_active'    => (bool) $validated['is_active'],
                'is_stackable' => false,
            ]);

            ProfileSkin::create([
                'shop_item_id'   => $shopItem->id,
                'name'           => $validated['name'],
                'slug'           => $slug,
                'description'    => $validated['description'],
                'preview_image_path' => $previewPath,
                'renderer_type'   => (string) ($validated['renderer_type'] ?? 'vue_template'),
                'template_key'   => $validated['template_key'],
                'background_image_path' => $this->storeOptionalImage($request, 'background_image'),
                'avatar_frame_image_path' => $this->storeOptionalImage($request, 'avatar_frame_image'),
                'panel_image_path' => $this->storeOptionalImage($request, 'panel_image'),
                'decoration_image_path' => $this->storeOptionalImage($request, 'decoration_image'),
                'hero_gradient'  => $validated['hero_gradient'],
                'accent_color'   => $validated['accent_color'],
                'border_color'   => $validated['border_color'],
                'glow_color'     => $validated['glow_color'],
                'stat_panel_bg'  => $validated['stat_panel_bg'],
                'text_primary'   => $validated['text_primary'],
                'is_active'      => (bool) $validated['is_active'],
            ]);
        });

        CacheVersion::bump('shop');

        return back()->with('message', 'PROFILE_SKIN_CREATED');
    }

    public function update(Request $request, ProfileSkin $skin): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:500'],
            'price_gold'     => ['required', 'integer', 'min:1'],
            'renderer_type'   => ['nullable', 'string', 'in:config,vue_template,project_static'],
            'template_key'   => ['required', 'string', 'in:default,void_phantom,arcade_cabinet,asset_showcase,project_static'],
            'hero_gradient'  => ['required', 'string', 'max:300'],
            'accent_color'   => ['required', 'string', 'max:30'],
            'border_color'   => ['required', 'string', 'max:30'],
            'glow_color'     => ['required', 'string', 'max:60'],
            'stat_panel_bg'  => ['required', 'string', 'max:30'],
            'text_primary'   => ['required', 'string', 'max:30'],
            'is_active'      => ['required', 'boolean'],
            'preview_image'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'background_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'avatar_frame_image' => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
            'panel_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'decoration_image' => ['nullable', 'image', 'mimes:png,webp', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, $validated, $skin) {
            if ($skin->shopItem) {
                $skin->shopItem->update([
                    'name'       => 'Skin: ' . $validated['name'],
                    'description' => $validated['description'],
                    'price_gold' => (int) $validated['price_gold'],
                    'is_active'  => (bool) $validated['is_active'],
                ]);
            }

            $previewPath = $skin->preview_image_path;
            if ($request->hasFile('preview_image')) {
                if ($previewPath && Storage::disk('public')->exists($previewPath)) {
                    Storage::disk('public')->delete($previewPath);
                }
                $previewPath = $request->file('preview_image')->store('profile-skins', 'public');

                if ($skin->shopItem) {
                    $skin->shopItem->update(['icon_path' => $previewPath]);
                }
            }

            $skin->update([
                'name'               => $validated['name'],
                'description'        => $validated['description'],
                'preview_image_path' => $previewPath,
                'renderer_type'       => (string) ($validated['renderer_type'] ?? ($skin->renderer_type ?: 'vue_template')),
                'template_key'        => $validated['template_key'],
                'background_image_path' => $this->replaceOptionalImage($request, 'background_image', $skin->background_image_path),
                'avatar_frame_image_path' => $this->replaceOptionalImage($request, 'avatar_frame_image', $skin->avatar_frame_image_path),
                'panel_image_path' => $this->replaceOptionalImage($request, 'panel_image', $skin->panel_image_path),
                'decoration_image_path' => $this->replaceOptionalImage($request, 'decoration_image', $skin->decoration_image_path),
                'hero_gradient'      => $validated['hero_gradient'],
                'accent_color'       => $validated['accent_color'],
                'border_color'       => $validated['border_color'],
                'glow_color'         => $validated['glow_color'],
                'stat_panel_bg'      => $validated['stat_panel_bg'],
                'text_primary'       => $validated['text_primary'],
                'is_active'          => (bool) $validated['is_active'],
            ]);
        });

        CacheVersion::bump('shop');

        return back()->with('message', 'PROFILE_SKIN_UPDATED');
    }

    public function importBundle(Request $request): RedirectResponse
    {
        return $this->handleBundleImport($request);
    }

    public function updateBundle(Request $request, ProfileSkin $skin): RedirectResponse
    {
        return $this->handleBundleImport($request, $skin);
    }

    public function syncExampleBundle(ProfileSkin $skin): RedirectResponse
    {
        $exampleRoot = $this->resolveServerExampleRoot($skin);
        if (! File::isDirectory($exampleRoot)) {
            return back()->withErrors([
                'bundle_files' => "Folder server public/examples/profile-skin-{$skin->slug} tidak ditemukan.",
            ]);
        }

        $manifestPath = "{$exampleRoot}/skin.json";
        $manifest = File::exists($manifestPath)
            ? json_decode(File::get($manifestPath), true)
            : [];

        if (! is_array($manifest)) {
            return back()->withErrors([
                'bundle_files' => 'skin.json di folder server tidak valid.',
            ]);
        }

        $skinData = $manifest['skin'] ?? $manifest;
        $manifestSlug = (string) ($skinData['slug'] ?? $skin->slug);
        if ($manifestSlug !== $skin->slug) {
            return back()->withErrors([
                'bundle_files' => "Slug skin.json ({$manifestSlug}) tidak sama dengan skin yang dipilih ({$skin->slug}).",
            ]);
        }

        $shopData = $manifest['shop'] ?? [];
        $assets = $manifest['assets'] ?? [];
        $project = $manifest['project'] ?? [];
        $config = $manifest['config'] ?? $skinData['config'] ?? null;
        $projectEntry = (string) ($project['entry'] ?? $skinData['project_entry'] ?? 'index.html');

        if (! File::exists("{$exampleRoot}/{$projectEntry}")) {
            return back()->withErrors([
                'bundle_files' => "File project entry {$projectEntry} tidak ditemukan di folder server.",
            ]);
        }

        DB::transaction(function () use ($skin, $skinData, $shopData, $assets, $manifest, $config, $exampleRoot, $projectEntry) {
            if ($skin->shopItem) {
                $skin->shopItem->update([
                    'name' => (string) ($shopData['name'] ?? $skin->shopItem->name),
                    'description' => (string) ($shopData['description'] ?? $skinData['description'] ?? $skin->shopItem->description),
                    'price_gold' => (int) ($shopData['price_gold'] ?? $skin->shopItem->price_gold),
                    'is_active' => (bool) ($shopData['is_active'] ?? $skinData['is_active'] ?? $skin->shopItem->is_active),
                ]);
            }

            $storedAssets = [
                'preview_image_path' => $this->copyLocalBundleAsset($exampleRoot, $assets['preview'] ?? null, $skin->slug, $skin->preview_image_path),
                'background_image_path' => $this->copyLocalBundleAsset($exampleRoot, $assets['background'] ?? null, $skin->slug, $skin->background_image_path),
                'avatar_frame_image_path' => $this->copyLocalBundleAsset($exampleRoot, $assets['avatar_frame'] ?? null, $skin->slug, $skin->avatar_frame_image_path),
                'panel_image_path' => $this->copyLocalBundleAsset($exampleRoot, $assets['panel'] ?? null, $skin->slug, $skin->panel_image_path),
                'decoration_image_path' => $this->copyLocalBundleAsset($exampleRoot, $assets['decoration'] ?? null, $skin->slug, $skin->decoration_image_path),
            ];

            if ($skin->shopItem && ! empty($storedAssets['preview_image_path'])) {
                $skin->shopItem->update(['icon_path' => $storedAssets['preview_image_path']]);
            }

            $storedProject = $this->copyLocalProjectBundle($exampleRoot, $skin->slug, $projectEntry, $skin->project_root_path);

            $skin->update([
                'name' => (string) ($skinData['name'] ?? $skin->name),
                'description' => (string) ($skinData['description'] ?? $skin->description),
                'renderer_type' => (string) ($skinData['renderer_type'] ?? 'project_static'),
                'template_key' => (string) ($skinData['template_key'] ?? 'project_static'),
                'bundle_root_path' => "profile-skins/{$skin->slug}",
                'hero_gradient' => (string) ($skinData['hero_gradient'] ?? $skin->hero_gradient),
                'accent_color' => (string) ($skinData['accent_color'] ?? $skin->accent_color),
                'border_color' => (string) ($skinData['border_color'] ?? $skin->border_color),
                'glow_color' => (string) ($skinData['glow_color'] ?? $skin->glow_color),
                'stat_panel_bg' => (string) ($skinData['stat_panel_bg'] ?? $skin->stat_panel_bg),
                'text_primary' => (string) ($skinData['text_primary'] ?? $skin->text_primary),
                'is_active' => (bool) ($skinData['is_active'] ?? $skin->is_active),
                'project_manifest' => $manifest,
                'config_json' => is_array($config) ? $config : $skin->config_json,
                ...array_filter($storedAssets, fn ($path) => ! is_null($path)),
                ...$storedProject,
            ]);
        });

        CacheVersion::bump('shop');

        return back()->with('message', 'PROFILE_SKIN_SYNCED_FROM_SERVER');
    }

    private function resolveServerExampleRoot(ProfileSkin $skin): string
    {
        $candidate = public_path("examples/profile-skin-{$skin->slug}");
        if (File::isDirectory($candidate)) {
            return $candidate;
        }

        return $candidate;
    }

    private function handleBundleImport(Request $request, ?ProfileSkin $targetSkin = null): RedirectResponse
    {
        $validated = $request->validate([
            'bundle_files' => ['required', 'array', 'min:2'],
            'bundle_files.*' => ['required', 'file', 'max:8192'],
            'relative_paths' => ['required', 'array', 'min:2'],
            'relative_paths.*' => ['required', 'string', 'max:500'],
        ]);

        $files = $request->file('bundle_files', []);
        $relativePaths = $validated['relative_paths'];
        $bundle = [];

        foreach ($files as $index => $file) {
            $relativePath = $this->normalizeBundlePath((string) ($relativePaths[$index] ?? $file->getClientOriginalName()));
            $bundle[$relativePath] = $file;
        }

        $manifestPath = collect(array_keys($bundle))
            ->first(fn (string $path) => basename($path) === 'skin.json');

        if (! $manifestPath) {
            if (! $targetSkin) {
                return back()->withErrors([
                    'bundle_files' => 'Folder skin wajib berisi file skin.json di root atau subfolder utama.',
                ]);
            }

            $manifest = [
                'skin' => [
                    'name' => $targetSkin->name,
                    'slug' => $targetSkin->slug,
                    'description' => $targetSkin->description,
                    'renderer_type' => 'project_static',
                    'template_key' => 'project_static',
                    'hero_gradient' => $targetSkin->hero_gradient,
                    'accent_color' => $targetSkin->accent_color,
                    'border_color' => $targetSkin->border_color,
                    'glow_color' => $targetSkin->glow_color,
                    'stat_panel_bg' => $targetSkin->stat_panel_bg,
                    'text_primary' => $targetSkin->text_primary,
                    'is_active' => $targetSkin->is_active,
                ],
                'shop' => [
                    'code' => $targetSkin->shopItem?->code,
                    'name' => $targetSkin->shopItem?->name,
                    'description' => $targetSkin->shopItem?->description,
                    'price_gold' => $targetSkin->shopItem?->price_gold,
                    'is_active' => $targetSkin->shopItem?->is_active,
                ],
                'project' => [
                    'entry' => 'index.html',
                ],
            ];
        } else {
            $manifest = json_decode((string) file_get_contents($bundle[$manifestPath]->getRealPath()), true);
        }

        if (! is_array($manifest)) {
            return back()->withErrors([
                'bundle_files' => 'skin.json tidak valid. Pastikan formatnya JSON object.',
            ]);
        }

        $skinData = $manifest['skin'] ?? $manifest;
        $shopData = $manifest['shop'] ?? [];
        $assets = $manifest['assets'] ?? [];
        $project = $manifest['project'] ?? [];
        $config = $manifest['config'] ?? $skinData['config'] ?? null;
        $manifestDir = $manifestPath
            ? trim(str_replace('\\', '/', dirname($manifestPath)), '.')
            : '';
        $manifestDir = $manifestDir === '' ? '' : trim($manifestDir, '/');

        $name = trim((string) ($skinData['name'] ?? ''));
        if ($name === '') {
            return back()->withErrors(['bundle_files' => 'skin.json wajib punya skin.name.']);
        }

        $slug = Str::slug((string) ($skinData['slug'] ?? $name));
        if ($slug === '') {
            return back()->withErrors(['bundle_files' => 'skin.json menghasilkan slug kosong.']);
        }

        if ($targetSkin && ProfileSkin::withTrashed()
            ->where('slug', $slug)
            ->whereKeyNot($targetSkin->getKey())
            ->exists()) {
            return back()->withErrors(['bundle_files' => 'Slug skin.json sudah dipakai skin lain.']);
        }

        $projectEntry = $project['entry'] ?? $skinData['project_entry'] ?? null;
        $rendererType = (string) ($skinData['renderer_type'] ?? $manifest['renderer_type'] ?? ($projectEntry ? 'project_static' : ($config ? 'config' : 'vue_template')));
        if (! in_array($rendererType, ['config', 'vue_template', 'project_static'], true)) {
            return back()->withErrors(['bundle_files' => 'renderer_type tidak dikenal.']);
        }

        $templateKey = (string) ($skinData['template_key'] ?? ($projectEntry ? 'project_static' : 'asset_showcase'));
        if (! in_array($templateKey, ['default', 'void_phantom', 'arcade_cabinet', 'asset_showcase', 'project_static'], true)) {
            return back()->withErrors(['bundle_files' => 'template_key tidak dikenal.']);
        }

        if ($rendererType === 'project_static' && ! $projectEntry) {
            return back()->withErrors(['bundle_files' => 'Skin project_static wajib punya project.entry di skin.json.']);
        }

        if (! $manifestPath && $projectEntry) {
            $entryBundlePath = $this->resolveBundlePath($bundle, (string) $projectEntry, '');
            $entryDirectory = $entryBundlePath ? trim(dirname($entryBundlePath), '.\\/') : '';
            $manifestDir = $entryDirectory === '' ? '' : $this->normalizeBundlePath($entryDirectory);
        }

        if ($projectEntry && ! $this->resolveBundlePath($bundle, (string) $projectEntry, $manifestDir)) {
            return back()->withErrors(['bundle_files' => 'File project.entry tidak ditemukan di folder skin.']);
        }

        $assetFields = [
            'preview_image_path' => $assets['preview'] ?? null,
            'background_image_path' => $assets['background'] ?? null,
            'avatar_frame_image_path' => $assets['avatar_frame'] ?? null,
            'panel_image_path' => $assets['panel'] ?? null,
            'decoration_image_path' => $assets['decoration'] ?? null,
        ];

        DB::transaction(function () use ($bundle, $slug, $name, $skinData, $shopData, $rendererType, $templateKey, $assetFields, $manifest, $config, $manifestDir, $projectEntry, $targetSkin) {
            $skin = $targetSkin ?: ProfileSkin::withTrashed()->where('slug', $slug)->first();
            if ($skin?->trashed()) {
                $skin->restore();
            }

            $shopCode = (string) ($shopData['code'] ?? ('SKIN_' . strtoupper(Str::snake($slug))));
            $shopItem = $skin?->shopItem ?: ShopItem::query()->firstOrNew(['code' => $shopCode]);
            $shopItem->fill([
                'code' => $shopCode,
                'name' => (string) ($shopData['name'] ?? ('Skin: ' . $name)),
                'description' => (string) ($shopData['description'] ?? ($skinData['description'] ?? '')),
                'price_gold' => (int) ($shopData['price_gold'] ?? $skinData['price_gold'] ?? 500),
                'is_active' => (bool) ($shopData['is_active'] ?? $skinData['is_active'] ?? true),
                'is_stackable' => false,
            ]);
            $shopItem->save();

            $storedAssets = [];
            foreach ($assetFields as $column => $relativeAssetPath) {
                $storedAssets[$column] = $this->storeBundleAsset(
                    $bundle,
                    $relativeAssetPath,
                    $slug,
                    $skin?->{$column},
                    $manifestDir
                );
            }

            if (! empty($storedAssets['preview_image_path'])) {
                $shopItem->update(['icon_path' => $storedAssets['preview_image_path']]);
            }

            $storedProject = $this->storeProjectBundle(
                $bundle,
                $slug,
                $manifestDir,
                $projectEntry,
                $skin?->project_root_path
            );

            $payload = [
                'shop_item_id' => $shopItem->id,
                'name' => $name,
                'slug' => $slug,
                'description' => (string) ($skinData['description'] ?? ''),
                'renderer_type' => $rendererType,
                'template_key' => $templateKey,
                'bundle_root_path' => "profile-skins/{$slug}",
                'hero_gradient' => (string) ($skinData['hero_gradient'] ?? 'linear-gradient(135deg, #0d1117 0%, #1a1c2c 100%)'),
                'accent_color' => (string) ($skinData['accent_color'] ?? '#4ed4d4'),
                'border_color' => (string) ($skinData['border_color'] ?? '#3d415f'),
                'glow_color' => (string) ($skinData['glow_color'] ?? 'rgba(78,212,212,0.2)'),
                'stat_panel_bg' => (string) ($skinData['stat_panel_bg'] ?? '#141b29'),
                'text_primary' => (string) ($skinData['text_primary'] ?? '#4ed4d4'),
                'is_active' => (bool) ($skinData['is_active'] ?? true),
                'project_manifest' => $manifest,
                'config_json' => is_array($config) ? $config : null,
                ...array_filter($storedAssets, fn ($path) => ! is_null($path)),
                ...array_filter($storedProject, fn ($path) => ! is_null($path)),
            ];

            if ($skin) {
                $skin->update($payload);
            } else {
                ProfileSkin::create($payload);
            }
        });

        CacheVersion::bump('shop');

        return back()->with('message', $targetSkin ? 'PROFILE_SKIN_BUNDLE_UPDATED' : 'PROFILE_SKIN_BUNDLE_IMPORTED');
    }

    public function destroy(ProfileSkin $skin): RedirectResponse
    {
        // Deactivate for all users who have this skin active
        $skin->users()->update(['active_profile_skin_id' => null]);

        if ($skin->shopItem) {
            $skin->shopItem->update(['is_active' => false]);
        }

        if ($skin->bundle_root_path && Storage::disk('public')->exists($skin->bundle_root_path)) {
            Storage::disk('public')->deleteDirectory($skin->bundle_root_path);
        } else {
            foreach ([
                $skin->preview_image_path,
                $skin->background_image_path,
                $skin->avatar_frame_image_path,
                $skin->panel_image_path,
                $skin->decoration_image_path,
            ] as $assetPath) {
                if ($assetPath && Storage::disk('public')->exists($assetPath)) {
                    Storage::disk('public')->delete($assetPath);
                }
            }

            if ($skin->project_root_path && Storage::disk('public')->exists($skin->project_root_path)) {
                Storage::disk('public')->deleteDirectory($skin->project_root_path);
            }
        }

        $skin->delete();

        CacheVersion::bump('shop');

        return back()->with('message', 'PROFILE_SKIN_DELETED');
    }

    private function makeUniqueShopCode(string $name): string
    {
        $baseCode = 'SKIN_' . strtoupper(Str::snake(Str::slug($name, '_')));
        $baseCode = preg_replace('/[^A-Z0-9_]/', '', $baseCode) ?: 'SKIN_PROFILE';
        $code = $baseCode;
        $i = 1;

        while (ShopItem::withTrashed()->where('code', $code)->exists()) {
            $code = "{$baseCode}_{$i}";
            $i++;
        }

        return $code;
    }

    private function storeOptionalImage(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        return $request->file($field)->store('profile-skins', 'public');
    }

    private function replaceOptionalImage(Request $request, string $field, ?string $currentPath): ?string
    {
        if (! $request->hasFile($field)) {
            return $currentPath;
        }

        if ($currentPath && Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        return $request->file($field)->store('profile-skins', 'public');
    }

    private function normalizeBundlePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?: '';
        return ltrim($path, '/');
    }

    private function storeBundleAsset(array $bundle, ?string $assetPath, string $slug, ?string $currentPath, string $manifestDir = ''): ?string
    {
        $assetPath = $assetPath ? $this->normalizeBundlePath($assetPath) : null;
        if (! $assetPath) {
            return $currentPath;
        }

        $file = $this->findBundleFile($bundle, $assetPath, $manifestDir);

        if (! $file) {
            return $currentPath;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($assetPath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $currentPath;
        }

        if ($currentPath && Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        $basename = Str::slug(pathinfo($assetPath, PATHINFO_FILENAME)) ?: Str::random(8);
        return $file->storeAs("profile-skins/{$slug}", "{$basename}.{$extension}", 'public');
    }

    private function copyLocalBundleAsset(string $sourceRoot, ?string $assetPath, string $slug, ?string $currentPath): ?string
    {
        $assetPath = $assetPath ? $this->normalizeBundlePath($assetPath) : null;
        if (! $assetPath) {
            return $currentPath;
        }

        $sourcePath = realpath($sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath));
        $sourceRootPath = realpath($sourceRoot);
        if (! $sourcePath || ! $sourceRootPath || ! str_starts_with(str_replace('\\', '/', $sourcePath), str_replace('\\', '/', $sourceRootPath))) {
            return $currentPath;
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $currentPath;
        }

        if ($currentPath && Storage::disk('public')->exists($currentPath)) {
            Storage::disk('public')->delete($currentPath);
        }

        $basename = Str::slug(pathinfo($assetPath, PATHINFO_FILENAME)) ?: Str::random(8);
        $targetPath = "profile-skins/{$slug}/{$basename}.{$extension}";
        Storage::disk('public')->put($targetPath, File::get($sourcePath));

        return $targetPath;
    }

    private function storeProjectBundle(array $bundle, string $slug, string $manifestDir, ?string $entryPath, ?string $currentRootPath): array
    {
        $entryPath = $entryPath ? $this->normalizeBundlePath($entryPath) : null;
        if (! $entryPath) {
            return [
                'project_entry_path' => null,
                'project_root_path' => $currentRootPath,
            ];
        }

        $entryBundlePath = $this->resolveBundlePath($bundle, $entryPath, $manifestDir);
        if (! $entryBundlePath) {
            return [
                'project_entry_path' => null,
                'project_root_path' => $currentRootPath,
            ];
        }

        $projectRoot = "profile-skins/{$slug}/project-" . now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        if ($currentRootPath && Storage::disk('public')->exists($currentRootPath)) {
            Storage::disk('public')->deleteDirectory($currentRootPath);
        }

        foreach ($bundle as $bundlePath => $file) {
            $relativePath = $this->relativeBundlePath($bundlePath, $manifestDir);
            if ($relativePath === 'skin.json' || str_starts_with($relativePath, '../')) {
                continue;
            }

            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            if (! $this->isAllowedProjectFile($extension)) {
                continue;
            }

            $directory = trim(dirname($relativePath), '.\\/');
            $targetDir = $directory === '' ? $projectRoot : "{$projectRoot}/{$directory}";
            $file->storeAs($targetDir, basename($relativePath), 'public');
        }

        return [
            'project_entry_path' => "{$projectRoot}/" . $this->relativeBundlePath($entryBundlePath, $manifestDir),
            'project_root_path' => $projectRoot,
        ];
    }

    private function copyLocalProjectBundle(string $sourceRoot, string $slug, string $entryPath, ?string $currentRootPath): array
    {
        $entryPath = $this->normalizeBundlePath($entryPath);
        $sourceRootPath = realpath($sourceRoot);
        $entryFullPath = realpath($sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryPath));
        if (! $sourceRootPath || ! $entryFullPath || ! str_starts_with(str_replace('\\', '/', $entryFullPath), str_replace('\\', '/', $sourceRootPath))) {
            return [
                'project_entry_path' => null,
                'project_root_path' => $currentRootPath,
            ];
        }

        $projectRoot = "profile-skins/{$slug}/project-" . now()->format('YmdHis') . '-' . Str::lower(Str::random(6));
        if ($currentRootPath && Storage::disk('public')->exists($currentRootPath)) {
            Storage::disk('public')->deleteDirectory($currentRootPath);
        }

        foreach (File::allFiles($sourceRoot) as $file) {
            $fullPath = str_replace('\\', '/', $file->getRealPath());
            $rootPath = rtrim(str_replace('\\', '/', $sourceRootPath), '/');
            $relativePath = ltrim(substr($fullPath, strlen($rootPath)), '/');
            if ($relativePath === 'skin.json' || str_starts_with($relativePath, '../')) {
                continue;
            }

            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            if (! $this->isAllowedProjectFile($extension)) {
                continue;
            }

            Storage::disk('public')->put("{$projectRoot}/{$relativePath}", File::get($file->getRealPath()));
        }

        return [
            'project_entry_path' => "{$projectRoot}/{$entryPath}",
            'project_root_path' => $projectRoot,
        ];
    }

    private function findBundleFile(array $bundle, string $relativePath, string $manifestDir = '')
    {
        $bundlePath = $this->resolveBundlePath($bundle, $relativePath, $manifestDir);

        return $bundlePath ? $bundle[$bundlePath] : null;
    }

    private function resolveBundlePath(array $bundle, string $relativePath, string $manifestDir = ''): ?string
    {
        $relativePath = $this->normalizeBundlePath($relativePath);
        $manifestRelativePath = $manifestDir !== ''
            ? $this->normalizeBundlePath("{$manifestDir}/{$relativePath}")
            : $relativePath;

        if (array_key_exists($manifestRelativePath, $bundle)) {
            return $manifestRelativePath;
        }

        if (array_key_exists($relativePath, $bundle)) {
            return $relativePath;
        }

        return collect(array_keys($bundle))->first(function (string $path) use ($relativePath) {
            return str_ends_with($path, '/' . $relativePath) || basename($path) === basename($relativePath);
        });
    }

    private function relativeBundlePath(string $bundlePath, string $manifestDir): string
    {
        $bundlePath = $this->normalizeBundlePath($bundlePath);
        $manifestDir = $this->normalizeBundlePath($manifestDir);

        if ($manifestDir !== '' && str_starts_with($bundlePath, "{$manifestDir}/")) {
            return substr($bundlePath, strlen($manifestDir) + 1);
        }

        return $bundlePath;
    }

    private function isAllowedProjectFile(string $extension): bool
    {
        return in_array($extension, [
            'html',
            'css',
            'js',
            'json',
            'png',
            'jpg',
            'jpeg',
            'webp',
            'gif',
            'svg',
            'woff',
            'woff2',
            'ttf',
            'otf',
            'mp3',
            'wav',
        ], true);
    }
}

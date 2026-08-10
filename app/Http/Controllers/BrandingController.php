<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    /**
     * Branding efectivo para el usuario autenticado: el suyo propio si es
     * médico, el de su médico si es secretaria, o valores vacíos si es
     * superadmin (no pertenece a ningún tenant). Solo lectura, para pintar
     * logo/nombre/color en el topbar y el membrete de los documentos.
     */
    public function show(Request $request)
    {
        return response()->json($this->brandingPayload($request->user()->tenant()));
    }

    /**
     * Branding de un médico puntual, consultado por el Superadmin desde la
     * gestión de usuarios.
     */
    public function showForUser(Request $request, int $id)
    {
        return response()->json($this->brandingPayload($this->resolveMedico($id)));
    }

    /**
     * Solo el Superadmin edita el branding, y siempre relacionándolo con la
     * cuenta del médico (admin) a la que pertenece: el médico ya no puede
     * cambiar su propio nombre/color/credenciales.
     */
    public function updateForUser(Request $request, int $id)
    {
        $medico = $this->resolveMedico($id);

        $request->validate([
            'brand_name' => 'nullable|string|max:255',
            'brand_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'brand_color_secondary' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'header_credentials' => 'nullable|string|max:1000',
            'licencia_declaracion' => 'nullable|string|max:1000',
        ]);

        $medico->update([
            'brand_name' => $request->brand_name,
            'brand_color' => $request->brand_color,
            'brand_color_secondary' => $request->brand_color_secondary,
            'header_credentials' => $request->header_credentials,
            'licencia_declaracion' => $request->licencia_declaracion,
        ]);

        return response()->json([
            'message' => 'Branding actualizado',
            ...$this->brandingPayload($medico),
        ]);
    }

    public function uploadLogoForUser(Request $request, int $id)
    {
        $medico = $this->resolveMedico($id);

        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $this->replaceImage($medico, 'logo_path', $request->file('logo'));

        return response()->json([
            'message' => 'Logo actualizado',
            ...$this->brandingPayload($medico),
        ]);
    }

    /**
     * Ícono/ilustración en el extremo izquierdo del header de los documentos.
     */
    public function uploadHeaderIconLeftForUser(Request $request, int $id)
    {
        $medico = $this->resolveMedico($id);

        $request->validate([
            'icon' => 'required|image|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $this->replaceImage($medico, 'header_icon_left_path', $request->file('icon'));

        return response()->json([
            'message' => 'Ícono izquierdo actualizado',
            ...$this->brandingPayload($medico),
        ]);
    }

    /**
     * Sello/ícono en el extremo derecho del header de los documentos.
     */
    public function uploadHeaderIconRightForUser(Request $request, int $id)
    {
        $medico = $this->resolveMedico($id);

        $request->validate([
            'icon' => 'required|image|mimes:jpg,jpeg,png,svg|max:2048',
        ]);

        $this->replaceImage($medico, 'header_icon_right_path', $request->file('icon'));

        return response()->json([
            'message' => 'Ícono derecho actualizado',
            ...$this->brandingPayload($medico),
        ]);
    }

    private function replaceImage(User $medico, string $column, UploadedFile $file): void
    {
        if ($medico->{$column}) {
            Storage::disk('public')->delete($medico->{$column});
        }

        $path = $file->store('branding', 'public');

        $medico->update([$column => $path]);
    }

    private function resolveMedico(int $id): User
    {
        $medico = User::findOrFail($id);

        abort_unless($medico->role === 'admin', 422, 'El usuario indicado no es un médico (admin).');

        return $medico;
    }

    private function brandingPayload(?User $tenant): array
    {
        return [
            'brand_name' => $tenant?->brand_name,
            'brand_color' => $tenant?->brand_color,
            'brand_color_secondary' => $tenant?->brand_color_secondary,
            'logo_url' => $tenant?->logo_url,
            'header_icon_left_url' => $tenant?->header_icon_left_url,
            'header_icon_right_url' => $tenant?->header_icon_right_url,
            'header_credentials' => $tenant?->header_credentials,
            'licencia_declaracion' => $tenant?->licencia_declaracion,
        ];
    }
}

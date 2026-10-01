<?php

namespace App\Controllers\Kurikulum;

use App\Controllers\BaseController;
use App\Libraries\BrandingService;
use App\Libraries\SafeImageUpload;
use App\Libraries\SettingsService;
use App\Models\AppSettingModel;

class PengaturanController extends BaseController
{
    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function index()
    {
        if (! \App\Models\UserModel::sessionIsKurikulumAdmin()) {
            return redirect()->to('/kurikulum/dashboard')
                ->with('error', 'Hanya kurikulum admin yang dapat mengakses Pengaturan.');
        }

        $model = new AppSettingModel();
        $settings = $model->orderBy('id', 'ASC')->first();
        if (! $settings) {
            $defaultName = (string) (SettingsService::get('school.name', 'Smart School Scheduling') ?? 'Smart School Scheduling');
            $model->insert([
                'nama_sekolah' => $defaultName,
                'logo_path'    => SettingsService::get('school.logo_path'),
            ]);
            $settings = $model->orderBy('id', 'ASC')->first();
        }

        return view('kurikulum/pengaturan/index', [
            'title'    => 'Pengaturan Aplikasi',
            'settings' => $settings,
            'branding' => BrandingService::get(),
        ]);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function update()
    {
        if (! \App\Models\UserModel::sessionIsKurikulumAdmin()) {
            return redirect()->to('/kurikulum/dashboard')
                ->with('error', 'Hanya kurikulum admin yang dapat mengubah Pengaturan.');
        }

        $rules = [
            'nama_sekolah' => 'required|min_length[3]|max_length[150]',
        ];

        $file = $this->request->getFile('logo');
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $rules['logo'] = 'uploaded[logo]|is_image[logo]|mime_in[logo,image/jpg,image/jpeg,image/png,image/webp]|max_size[logo,2048]';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new AppSettingModel();
        $settings = $model->orderBy('id', 'ASC')->first();
        if (! $settings) {
            $id = $model->insert([
                'nama_sekolah' => $this->request->getPost('nama_sekolah'),
                'logo_path'    => null,
            ], true);
            $settings = $model->find($id);
        }

        $data = [
            'nama_sekolah' => trim((string) $this->request->getPost('nama_sekolah')),
        ];

        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $ext = SafeImageUpload::resolveExtension($file->guessExtension());
            if ($ext === null) {
                return redirect()->back()->withInput()->with(
                    'error',
                    'Format logo tidak didukung. Gunakan PNG, JPG, atau WebP.',
                );
            }

            $dir = FCPATH . 'uploads/branding';
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $newName = 'logo_' . time() . '.' . $ext;
            $file->move($dir, $newName);

            $old = $settings['logo_path'] ?? null;
            if (is_string($old) && str_starts_with($old, 'uploads/branding/') && is_file(FCPATH . $old)) {
                @unlink(FCPATH . $old);
            }

            $data['logo_path'] = 'uploads/branding/' . $newName;
        }

        if ($this->request->getPost('hapus_logo') === '1' && empty($data['logo_path'])) {
            $old = $settings['logo_path'] ?? null;
            if (is_string($old) && str_starts_with($old, 'uploads/branding/') && is_file(FCPATH . $old)) {
                @unlink(FCPATH . $old);
            }
            $data['logo_path'] = null;
        }

        $model->update($settings['id'], $data);

        $userId = (int) session()->get('user_id');
        SettingsService::set('school.name', $data['nama_sekolah'], 'string', $userId > 0 ? $userId : null);
        if (array_key_exists('logo_path', $data)) {
            SettingsService::set('school.logo_path', $data['logo_path'], 'string', $userId > 0 ? $userId : null);
        }

        BrandingService::clearCache();

        return redirect()->to('/kurikulum/pengaturan')->with('success', 'Pengaturan berhasil disimpan.');
    }
}

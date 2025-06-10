<?php

namespace App\Http\Controllers;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleServiceDrive;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class GoogleDriveController extends Controller
{
    protected GoogleServiceDrive $driveService;

    public function __construct()
    {
        $config = config('filesystems.disks.google');

        $client = new GoogleClient();
        $client->setAuthConfig(base_path($config['credentials_json']));
        $client->setScopes([GoogleServiceDrive::DRIVE_READONLY]);

        $this->driveService = new GoogleServiceDrive($client);
    }

    public function index()
    {
        return view('google-drive.upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'webinar_id' => 'required|integer',
            'folder_id' => 'required|string'
        ]);

        try
        {
            $tree = $this->getFolderTree($request->folder_id);
            $this->syncToApp($tree, null, $request->webinar_id);
            return redirect()->back()->with('success', 'Files uploaded successfully!');
        }
        catch (\Exception $e)
        {
            return redirect()->back()->with('error', 'Error uploading files: ' . $e->getMessage());
        }
    }

    private function getFolderTree(string $parentId): array
    {
        $acc = [];
        $pageToken = null;

        do
        {
            $response = $this->driveService->files->listFiles([
                'q' => "'{$parentId}' in parents and trashed = false",
                'fields' => 'nextPageToken, files(id, name, mimeType, size, webContentLink, webViewLink, fileExtension)',
                'pageSize' => 100,
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getFiles() as $file)
            {
                $item = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mimeType' => $file->getMimeType(),
                ];

                if ($file->getMimeType() === 'application/vnd.google-apps.folder')
                {
                    $item['children'] = $this->getFolderTree($file->getId());
                }
                else
                {
                    $item['size'] = (int) $file->getSize();
                    $item['downloadUrl'] = $file->getWebContentLink();
                    $item['viewUrl'] = $file->getWebViewLink();
                    $item['fileType'] = $file->getFileExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION);
                }

                $acc[] = $item;
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $acc;
    }

    protected function syncToApp(array $items, ?int $parentChapterId = null, int $webinarId): void
    {
        $baseUrl = env('API_URL');
        foreach ($items as $item)
        {
            if ($item['mimeType'] === 'application/vnd.google-apps.folder')
            {
                $chapResp = Http::withHeader('x-api-key', '5612')->timeout(60)
                    ->post($baseUrl . '/chapters/store/google-drive', [
                        'title' => $item['name'],
                        'webinar_id' => $webinarId,
                    ]);

                $chapterId = $chapResp->json()['id'] ?? null;
                if (!$chapterId)
                {
                    continue;
                }

                if (!empty($item['children']))
                {
                    $this->syncToApp($item['children'], $chapterId, $webinarId);
                }
            }
            else
            {
                if (!$parentChapterId)
                {
                    Log::warning("Skipping file \"{$item['name']}\" because no chapter ID was provided.");
                    continue;
                }

                $volumeMb = round(($item['size'] ?? 0) / 1024 / 1024, 2);

                $fileType = Str::startsWith($item['mimeType'], 'video/')
                    ? 'video'
                    : $item['fileType'];

                $filePayload = [
                    'webinar_id' => $webinarId,
                    'chapter_id' => $parentChapterId,
                    'title' => $item['name'],
                    'file_path' => $item['viewUrl'],
                    'storage' => 'google_drive',
                    'file_type' => $fileType,
                    'volume' => $volumeMb,
                    'accessibility' => 'free',
                    'description' => '',
                ];

                Http::withHeader('x-api-key', '5612')->timeout(60)
                    ->post($baseUrl . '/files/store/google-drive', $filePayload);
            }
        }
    }
}

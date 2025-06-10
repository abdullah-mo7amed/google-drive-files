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
        $this->middleware(['auth']);

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
        Log::info('Starting Google Drive upload process', [
            'user_id' => auth()->id(),
            'is_admin' => auth()->user()->is_admin,
            'webinar_id' => $request->webinar_id,
            'folder_id' => $request->folder_id
        ]);

        $request->merge(['is_admin' => auth()->user()->is_admin]);

        $request->validate([
            'webinar_id' => 'required|integer',
            'folder_id' => 'required|string',
            'api_url' => 'required_if:is_admin,true|url|nullable'
        ]);

        try
        {
            Log::info('Fetching folder tree from Google Drive', [
                'folder_id' => $request->folder_id
            ]);

            $tree = $this->getFolderTree($request->folder_id);

            Log::info('Folder tree fetched successfully', [
                'total_items' => count($tree)
            ]);

            // If admin is uploading for a specific API URL
            if (auth()->user()->is_admin && $request->api_url)
            {
                $apiUrl = $request->api_url;
                Log::info('Admin using custom API URL', ['api_url' => $apiUrl]);
            }
            else
            {
                $apiUrl = auth()->user()->api_url;
                Log::info('Using user default API URL', ['api_url' => $apiUrl]);
            }

            $this->syncToApp($tree, null, $request->webinar_id, $apiUrl);

            Log::info('Upload process completed successfully');
            return redirect()->back()->with('success', 'Files uploaded successfully!');
        }
        catch (\Exception $e)
        {
            Log::error('Error during upload process', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error uploading files: ' . $e->getMessage());
        }
    }

    private function getFolderTree(string $parentId): array
    {
        $acc = [];
        $pageToken = null;
        $pageCount = 0;

        do
        {
            $pageCount++;
            Log::info('Fetching page from Google Drive', [
                'page_number' => $pageCount,
                'parent_id' => $parentId
            ]);

            $response = $this->driveService->files->listFiles([
                'q' => "'{$parentId}' in parents and trashed = false",
                'fields' => 'nextPageToken, files(id, name, mimeType, size, webContentLink, webViewLink, fileExtension)',
                'pageSize' => 100,
                'pageToken' => $pageToken,
            ]);

            $files = $response->getFiles();
            Log::info('Files fetched from page', [
                'page_number' => $pageCount,
                'files_count' => count($files)
            ]);

            foreach ($files as $file)
            {
                $item = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mimeType' => $file->getMimeType(),
                ];

                if ($file->getMimeType() === 'application/vnd.google-apps.folder')
                {
                    Log::info('Found folder, fetching its contents', [
                        'folder_name' => $file->getName(),
                        'folder_id' => $file->getId()
                    ]);
                    $item['children'] = $this->getFolderTree($file->getId());
                }
                else
                {
                    $item['size'] = (int) $file->getSize();
                    $item['downloadUrl'] = $file->getWebContentLink();
                    $item['viewUrl'] = $file->getWebViewLink();
                    $item['fileType'] = $file->getFileExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION);
                    Log::info('File details', [
                        'file_name' => $file->getName(),
                        'file_type' => $item['fileType'],
                        'file_size' => $item['size']
                    ]);
                }

                $acc[] = $item;
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $acc;
    }

    protected function syncToApp(array $items, ?int $parentChapterId = null, int $webinarId, string $apiUrl): void
    {
        foreach ($items as $item)
        {
            if ($item['mimeType'] === 'application/vnd.google-apps.folder')
            {
                Log::info('Creating chapter for folder', [
                    'folder_name' => $item['name'],
                    'webinar_id' => $webinarId
                ]);

                $chapResp = Http::withHeader('x-api-key', '5612')->timeout(60)
                    ->post($apiUrl . '/chapters/store/google-drive', [
                        'title' => $item['name'],
                        'webinar_id' => $webinarId,
                    ]);

                $chapterId = $chapResp->json()['id'] ?? null;
                if (!$chapterId)
                {
                    Log::warning('Failed to create chapter', [
                        'folder_name' => $item['name'],
                        'response' => $chapResp->json()
                    ]);
                    continue;
                }

                Log::info('Chapter created successfully', [
                    'chapter_id' => $chapterId,
                    'chapter_name' => $item['name']
                ]);

                if (!empty($item['children']))
                {
                    $this->syncToApp($item['children'], $chapterId, $webinarId, $apiUrl);
                }
            }
            else
            {
                if (!$parentChapterId)
                {
                    Log::warning('Skipping file - no chapter ID', [
                        'file_name' => $item['name']
                    ]);
                    continue;
                }

                $volumeMb = round(($item['size'] ?? 0) / 1024 / 1024, 2);

                $fileType = Str::startsWith($item['mimeType'], 'video/')
                    ? 'video'
                    : $item['fileType'];

                Log::info('Uploading file', [
                    'file_name' => $item['name'],
                    'file_type' => $fileType,
                    'file_size_mb' => $volumeMb,
                    'chapter_id' => $parentChapterId
                ]);

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

                $fileResp = Http::withHeader('x-api-key', '5612')->timeout(60)
                    ->post($apiUrl . '/files/store/google-drive', $filePayload);

                Log::info('File upload response', [
                    'file_name' => $item['name'],
                    'response' => $fileResp->json()
                ]);
            }
        }
    }
}

<?php
namespace App\Http\Controllers;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleServiceDrive;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $rootFolderId = config('filesystems.disks.google.folder');

        $tree = $this->getFolderTree($rootFolderId);
        $this->syncToApp($tree);

        return response()->json([
            'status'   => 'ok',
            'synced'   => true,
            'children' => $tree,
        ]);
    }

    private function getFolderTree(string $parentId): array
    {
        $acc       = [];
        $pageToken = null;

        do {
            $response = $this->driveService->files->listFiles([
                // نطلب حقول view و content و امتداد الملف
                'q'         => "'{$parentId}' in parents and trashed = false",
                'fields'    => 'nextPageToken, files(id, name, mimeType, size, webContentLink, webViewLink, fileExtension)',
                'pageSize'  => 100,
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getFiles() as $file) {
                $item = [
                    'id'       => $file->getId(),
                    'name'     => $file->getName(),
                    'mimeType' => $file->getMimeType(),
                ];

                if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                    $item['children'] = $this->getFolderTree($file->getId());
                } else {
                    $item['size']        = (int) $file->getSize();     // بالبايت
                    $item['downloadUrl'] = $file->getWebContentLink(); // رابط التحميل
                    $item['viewUrl']     = $file->getWebViewLink();    // رابط العرض
                    $item['fileType']    = $file->getFileExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION);
                }

                $acc[] = $item;
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $acc;
    }

    protected function syncToApp(array $items, int $parentChapterId = null): void
    {
        foreach ($items as $item) {
            if ($item['mimeType'] === 'application/vnd.google-apps.folder') {
                // تنشئة القسم (Chapter)
                $chapResp = Http::withHeader('x-api-key', '5612')
                    ->post('https://appmawso3aonline.anmka.com/api/chapters/store', [
                        'title' => $item['name'],
                    ]);

                Log::info('Chapter create response:', $chapResp->json());
                $chapterId = $chapResp->json()['id'] ?? null;
                if (! $chapterId) {
                    Log::warning("Failed to create chapter “{$item['name']}”");
                    continue;
                }

                if (! empty($item['children'])) {
                    $this->syncToApp($item['children'], $chapterId);
                }

            } else {
                if (! $parentChapterId) {
                    Log::warning("Skipping file “{$item['name']}” because no chapter ID was provided.");
                    continue;
                }

                // تحويل الحجم من بايت إلى ميغابايت مع تقريب لمرتين عشرية
                $volumeMb = round(($item['size'] ?? 0) / 1024 / 1024, 2);

                // إذا كان الفيديو، نجعل file_type=video وإلا نترك الامتداد
                $fileType = Str::startsWith($item['mimeType'], 'video/')
                ? 'video'
                : $item['fileType'];

                $filePayload = [
                    'webinar_id'    => 2039,
                    'chapter_id'    => $parentChapterId,
                    'title'         => $item['name'],
                    'file_path'     => $item['viewUrl'], // سيعالج كملف google_drive
                    'storage'       => 'google_drive',
                    'file_type'     => $fileType,
                    'volume'        => $volumeMb,
                    'accessibility' => 'free',
                    'description'   => '', // أو تحط وصف لو عندك
                ];

                Log::info('Sending file payload:', $filePayload);
                $fileResp = Http::withHeader('x-api-key', '5612')
                    ->post('https://appmawso3aonline.anmka.com/api/files/store', $filePayload);

                Log::info('File create response:', $fileResp->json());
            }
        }
    }
}

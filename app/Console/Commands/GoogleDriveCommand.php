<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleServiceDrive;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleDriveCommand extends Command
{
    protected $signature = 'upload:google-drive';
    protected $description = 'Upload files from Google Drive to the app';

    protected GoogleServiceDrive $driveService;
    public $webinarId;
    public $folderId;

    public function handle()
    {
        $config = config('filesystems.disks.google');

        $client = new GoogleClient();
        $client->setAuthConfig(base_path($config['credentials_json']));
        $client->setScopes([GoogleServiceDrive::DRIVE_READONLY]);

        $this->driveService = new GoogleServiceDrive($client);

        $tree = $this->getFolderTree($this->folderId);

        $this->syncToApp($tree);

        $this->info('Sync completed successfully.');
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

    protected function syncToApp(array $items, int $parentChapterId = null): void
    {
        foreach ($items as $item)
        {
            if ($item['mimeType'] === 'application/vnd.google-apps.folder')
            {
                $chapResp = Http::withHeader('x-api-key', '5612')->timeout(60)
                    ->post('https://appmawso3aonline.anmka.com/api/chapters/store', [
                        'title' => $item['name'],
                        'webinar_id' => $this->webinarId,
                    ]);

                // Log::info('Chapter create response:', $chapResp->json());

                $chapterId = $chapResp->json()['id'] ?? null;
                if (!$chapterId)
                {
                    // Log::warning("Failed to create chapter \"{$item['name']}\"");
                    continue;
                }

                if (!empty($item['children']))
                {
                    $this->syncToApp($item['children'], $chapterId);
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
                    'webinar_id' => $this->webinarId,
                    'chapter_id' => $parentChapterId,
                    'title' => $item['name'],
                    'file_path' => $item['viewUrl'],
                    'storage' => 'google_drive',
                    'file_type' => $fileType,
                    'volume' => $volumeMb,
                    'accessibility' => 'free',
                    'description' => '',
                ];

                // Log::info('Sending file payload:', $filePayload);

                $fileResp = Http::withHeader('x-api-key', '5612')->timeout(60)
                    ->post('https://appmawso3aonline.anmka.com/api/files/store', $filePayload);

                // Log::info('File create response:', $fileResp->json());
            }
        }
    }
}

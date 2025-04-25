<?php

namespace App\Http\Controllers;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleServiceDrive;
use Illuminate\Routing\Controller;

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

    $allFiles = $this->listAllFiles('1SPlODE4YSuH-z5lQMG4B4IXMeuatrwE1');

    return response()->json([
      'count' => count($allFiles),
      'files' => $allFiles,
    ]);
  }

  private function listAllFiles(string $parentId, array &$acc = []): array
  {
    $pageToken = null;

    do
    {
      $response = $this->driveService->files->listFiles([
        'q'         => "'{$parentId}' in parents and trashed = false",
        'fields'    => 'nextPageToken, files(id, name, mimeType, size, webContentLink)',
        'pageSize'  => 100,
        'pageToken' => $pageToken,
      ]);

      foreach ($response->getFiles() as $file)
      {
        if ($file->getMimeType() === 'application/vnd.google-apps.folder')
        {
          $this->listAllFiles($file->getId(), $acc);
        }
        else
        {
          $acc[] = [
            'id'       => $file->getId(),
            'name'     => $file->getName(),
            'mimeType' => $file->getMimeType(),
            'size'     => $file->getSize(),
            'url'      => $file->getWebContentLink(),
          ];
        }
      }

      $pageToken = $response->getNextPageToken();
    } while ($pageToken);

    return $acc;
  }
}

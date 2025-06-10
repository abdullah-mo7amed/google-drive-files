<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Google Drive Upload</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
  <div class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header">
            <h3 class="text-center">Google Drive Upload</h3>
          </div>
          <div class="card-body">
            @if(session('success'))
            <div class="alert alert-success">
              {{ session('success') }}
            </div>
            @endif

            @if(session('error'))
            <div class="alert alert-danger">
              {{ session('error') }}
            </div>
            @endif

            <form action="{{ route('google-drive.upload') }}" method="POST">
              @csrf
              <div class="mb-3">
                <label for="webinar_id" class="form-label">Webinar ID</label>
                <input type="number" class="form-control" id="webinar_id" name="webinar_id" required>
              </div>
              <div class="mb-3">
                <label for="folder_id" class="form-label">Google Drive Folder ID</label>
                <input type="text" class="form-control" id="folder_id" name="folder_id" required>
              </div>
              <div class="d-grid">
                <button type="submit" class="btn btn-primary">Upload Files</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
/**
 * GLOBAL FILE RENAMER
 * Script untuk mencari dan rename file dengan nama tertentu di semua subdirektori
 */

class GlobalFileRenamer {
    private $processedFiles = [];
    private $errors = [];
    private $dryRun = true;
    
    /**
     * Find all files with specific name in directory and subdirectories
     */
    public function findAndRenameFiles($basePath, $oldFileName, $newFileName) {
        if (!is_dir($basePath)) {
            $this->errors[] = "Direktori tidak ditemukan: $basePath";
            return;
        }
        
        // Find all files recursively
        $files = $this->findFilesByName($basePath, $oldFileName);
        
        if (empty($files)) {
            $this->errors[] = "Tidak ditemukan file dengan nama: $oldFileName di $basePath";
            return;
        }
        
        // Rename each found file
        foreach ($files as $oldFullPath) {
            $dirPath = dirname($oldFullPath);
            $newFullPath = $dirPath . '/' . $newFileName;
            
            $this->renameFile($oldFullPath, $newFullPath);
        }
    }
    
    /**
     * Recursive function to find files by name
     */
    private function findFilesByName($dir, $fileName, &$results = []) {
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            
            if (is_file($path) && $item === $fileName) {
                $results[] = $path;
            }
            
            if (is_dir($path)) {
                $this->findFilesByName($path, $fileName, $results);
            }
        }
        
        return $results;
    }
    
    /**
     * Rename file with validation
     */
    private function renameFile($oldPath, $newPath) {
        // Validasi file exists
        if (!file_exists($oldPath)) {
            $this->errors[] = "File tidak ditemukan: " . basename($oldPath);
            return false;
        }
        
        // Check if new file already exists
        if (file_exists($newPath)) {
            $relativePath = str_replace($_POST['base_path'] . '/', '', $newPath);
            $this->errors[] = "File tujuan sudah ada: $relativePath";
            return false;
        }
        
        // Execute rename
        if (!$this->dryRun) {
            if (rename($oldPath, $newPath)) {
                $this->processedFiles[] = [
                    'old_path' => $oldPath,
                    'new_path' => $newPath,
                    'old_name' => basename($oldPath),
                    'new_name' => basename($newPath),
                    'directory' => dirname($oldPath),
                    'status' => 'success',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                return true;
            } else {
                $this->errors[] = "Gagal rename: " . basename($oldPath);
                return false;
            }
        } else {
            // Preview mode
            $this->processedFiles[] = [
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'old_name' => basename($oldPath),
                'new_name' => basename($newPath),
                'directory' => dirname($oldPath),
                'status' => 'preview',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            return true;
        }
    }
    
    /**
     * Set dry run mode
     */
    public function setDryRun($dryRun) {
        $this->dryRun = $dryRun;
    }
    
    /**
     * Get results
     */
    public function getResults() {
        return [
            'processed' => $this->processedFiles,
            'errors' => $this->errors,
            'total' => count($this->processedFiles),
            'mode' => $this->dryRun ? 'preview' : 'execute'
        ];
    }
}

// ============================================
// HTML INTERFACE
// ============================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GLOBAL FILE RENAMER - Rename File di Semua Subdirektori</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
        }
        
        .form-section, .results-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        
        .section-title {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            font-size: 1.4rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .example-box {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.9rem;
            border-left: 4px solid #667eea;
        }
        
        .example-title {
            font-weight: bold;
            color: #495057;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .example-code {
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 5px 0;
            border: 1px solid #dee2e6;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 160px;
            justify-content: center;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-preview {
            background: #17a2b8;
            color: white;
        }
        
        .btn-preview:hover {
            background: #138496;
        }
        
        .btn-execute {
            background: #28a745;
            color: white;
        }
        
        .btn-execute:hover {
            background: #218838;
        }
        
        .btn-reset {
            background: #6c757d;
            color: white;
        }
        
        .btn-reset:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.95rem;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .results-container {
            max-height: 500px;
            overflow-y: auto;
            margin-top: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: white;
        }
        
        .file-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s;
        }
        
        .file-item:hover {
            background-color: #f8f9fa;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-directory {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .file-names {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .file-old {
            color: #dc3545;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .file-new {
            color: #28a745;
            font-weight: 500;
            font-size: 1rem;
        }
        
        .file-arrow {
            color: #6c757d;
            font-size: 1.2rem;
        }
        
        .file-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .status-preview {
            background: #ffc107;
            color: #856404;
        }
        
        .status-success {
            background: #28a745;
            color: white;
        }
        
        .counter {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 15px 0;
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .summary {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #dee2e6;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .summary-label {
            color: #6c757d;
        }
        
        .summary-value {
            font-weight: 600;
            color: #333;
        }
        
        .loader {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .icon {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌍 GLOBAL FILE RENAMER</h1>
            <p>Rename file dengan nama tertentu di semua subdirektori secara massal</p>
        </div>
        
        <div class="content">
            <div class="form-section">
                <h2 class="section-title">⚙️ Konfigurasi</h2>
                
                <div class="alert alert-warning">
                    <div class="icon">⚠️</div>
                    <div>
                        <strong>PENTING:</strong> Script ini akan mencari file dengan nama tertentu 
                        di SEMUA subfolder dan mengganti namanya. Pastikan sudah backup data!
                    </div>
                </div>
                
                <form id="renameForm" method="post">
                    <div class="form-group">
                        <label for="base_path">Path Utama (Root Directory):</label>
                        <input type="text" id="base_path" name="base_path" 
                               value="<?php echo htmlspecialchars($_POST['base_path'] ?? ''); ?>"
                               placeholder="/home/euclair"
                               required>
                        <div class="example-box">
                            <div class="example-title">📁 Contoh:</div>
                            <div class="example-code">/home/euclair</div>
                            <div class="example-code">C:\xampp\htdocs\projects</div>
                            <div class="example-code">/var/www/html</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="old_file_name">Nama File yang akan di-rename:</label>
                        <input type="text" id="old_file_name" name="old_file_name" 
                               value="<?php echo htmlspecialchars($_POST['old_file_name'] ?? ''); ?>"
                               placeholder="tes.txt"
                               required>
                        <div class="example-box">
                            <div class="example-title">📄 Contoh File yang akan dicari:</div>
                            <div class="example-code">tes.txt</div>
                            <div class="example-code">index.html</div>
                            <div class="example-code">config.php</div>
                            <div class="example-code">logo.png</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_file_name">Nama File Baru:</label>
                        <input type="text" id="new_file_name" name="new_file_name" 
                               value="<?php echo htmlspecialchars($_POST['new_file_name'] ?? ''); ?>"
                               placeholder="tes-baru.txt"
                               required>
                        <div class="example-box">
                            <div class="example-title">🔄 Contoh Nama Baru:</div>
                            <div class="example-code">tes-baru.txt</div>
                            <div class="example-code">index-old.html</div>
                            <div class="example-code">config-backup.php</div>
                            <div class="example-code">logo-new.png</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Mode Eksekusi:</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="mode" value="preview" 
                                       <?php echo ($_POST['mode'] ?? 'preview') == 'preview' ? 'checked' : ''; ?>> 
                                <span>🔍 Preview (Hanya tampilkan)</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="mode" value="execute" 
                                       <?php echo ($_POST['mode'] ?? '') == 'execute' ? 'checked' : ''; ?>> 
                                <span>🚀 Execute (Lakukan rename)</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="action" value="process" class="btn btn-preview">
                            <span class="icon">🔍</span>
                            Cari & Preview
                        </button>
                        <button type="submit" name="action" value="execute" class="btn btn-execute"
                                onclick="return confirm('⚠️ YAKIN ingin melakukan rename?\\n\\nFile dengan nama \'' + document.getElementById('old_file_name').value + '\' di SEMUA subfolder akan diubah menjadi \'' + document.getElementById('new_file_name').value + '\'')">
                            <span class="icon">🚀</span>
                            Execute Rename
                        </button>
                        <button type="reset" class="btn btn-reset">
                            <span class="icon">🔄</span>
                            Reset Form
                        </button>
                    </div>
                </form>
                
                <div class="loader" id="loader">
                    <div class="spinner"></div>
                    <p>Mencari file...</p>
                </div>
            </div>
            
            <div class="results-section">
                <h2 class="section-title">📊 Hasil Pencarian & Rename</h2>
                
                <?php
                // Proses form submission
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['base_path'])) {
                    $renamer = new GlobalFileRenamer();
                    
                    // Set mode
                    $isExecute = ($_POST['action'] ?? '') === 'execute';
                    $renamer->setDryRun(!$isExecute);
                    
                    try {
                        // Eksekusi pencarian dan rename
                        $renamer->findAndRenameFiles(
                            $_POST['base_path'],
                            $_POST['old_file_name'],
                            $_POST['new_file_name']
                        );
                        
                        $results = $renamer->getResults();
                        
                        // Display mode info
                        if ($isExecute) {
                            echo '<div class="alert alert-success">';
                            echo '<div class="icon">✅</div>';
                            echo '<div><strong>EXECUTE MODE:</strong> Rename telah dieksekusi pada ' . count($results['processed']) . ' file!</div>';
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-info">';
                            echo '<div class="icon">🔍</div>';
                            echo '<div><strong>PREVIEW MODE:</strong> Ditemukan ' . count($results['processed']) . ' file yang akan di-rename</div>';
                            echo '</div>';
                        }
                        
                        // Display summary
                        echo '<div class="summary">';
                        echo '<div class="summary-item">';
                        echo '<span class="summary-label">Path Utama:</span>';
                        echo '<span class="summary-value">' . htmlspecialchars($_POST['base_path']) . '</span>';
                        echo '</div>';
                        
                        echo '<div class="summary-item">';
                        echo '<span class="summary-label">Nama File Lama:</span>';
                        echo '<span class="summary-value">' . htmlspecialchars($_POST['old_file_name']) . '</span>';
                        echo '</div>';
                        
                        echo '<div class="summary-item">';
                        echo '<span class="summary-label">Nama File Baru:</span>';
                        echo '<span class="summary-value">' . htmlspecialchars($_POST['new_file_name']) . '</span>';
                        echo '</div>';
                        
                        echo '<div class="summary-item">';
                        echo '<span class="summary-label">Total File Ditemukan:</span>';
                        echo '<span class="summary-value">' . $results['total'] . ' file</span>';
                        echo '</div>';
                        echo '</div>';
                        
                        // Display errors
                        if (!empty($results['errors'])) {
                            echo '<div class="alert alert-danger">';
                            echo '<div class="icon">❌</div>';
                            echo '<div>';
                            echo '<strong>Error (' . count($results['errors']) . '):</strong>';
                            echo '<ul style="margin-top: 10px; margin-left: 20px;">';
                            foreach ($results['errors'] as $error) {
                                echo '<li>' . htmlspecialchars($error) . '</li>';
                            }
                            echo '</ul>';
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        // Display results
                        if (!empty($results['processed'])) {
                            echo '<div class="counter">';
                            echo '📁 Ditemukan ' . $results['total'] . ' file di ' . 
                                 count(array_unique(array_column($results['processed'], 'directory'))) . ' direktori';
                            echo '</div>';
                            
                            echo '<div class="results-container">';
                            foreach ($results['processed'] as $index => $file) {
                                $relativePath = str_replace($_POST['base_path'] . '/', '', $file['directory']);
                                
                                echo '<div class="file-item">';
                                echo '<div class="file-directory">';
                                echo '<span class="icon">📁</span>';
                                echo ($relativePath === $file['directory']) ? 
                                     htmlspecialchars($file['directory']) : 
                                     htmlspecialchars($relativePath);
                                echo '</div>';
                                
                                echo '<div class="file-names">';
                                echo '<span class="file-old">' . htmlspecialchars($file['old_name']) . '</span>';
                                echo '<span class="file-arrow">→</span>';
                                echo '<span class="file-new">' . htmlspecialchars($file['new_name']) . '</span>';
                                echo '</div>';
                                
                                echo '<div>';
                                echo '<span class="file-status status-' . $file['status'] . '">';
                                echo $file['status'] === 'success' ? '✓ Renamed' : '👁️ Preview';
                                echo '</span>';
                                echo '</div>';
                                echo '</div>';
                            }
                            echo '</div>';
                            
                            // Info tambahan jika dalam preview mode
                            if (!$isExecute && $results['total'] > 0) {
                                echo '<div class="alert alert-warning" style="margin-top: 20px;">';
                                echo '<div class="icon">💡</div>';
                                echo '<div>';
                                echo '<strong>Info:</strong> File di atas hanya preview. ';
                                echo 'Klik "Execute Rename" untuk benar-benar mengganti nama file.';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="alert alert-info">';
                            echo '<div class="icon">ℹ️</div>';
                            echo '<div>Tidak ditemukan file dengan nama: <strong>' . 
                                 htmlspecialchars($_POST['old_file_name']) . '</strong> di ' . 
                                 htmlspecialchars($_POST['base_path']) . '</div>';
                            echo '</div>';
                        }
                        
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">';
                        echo '<div class="icon">❌</div>';
                        echo '<div><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert alert-info">';
                    echo '<div class="icon">👈</div>';
                    echo '<div>Masukkan konfigurasi di panel kiri untuk mencari dan rename file</div>';
                    echo '</div>';
                    
                    // Contoh output
                    echo '<div class="example-box" style="margin-top: 20px;">';
                    echo '<div class="example-title">📋 Contoh Hasil:</div>';
                    echo '<div style="margin-top: 10px;">';
                    echo '<div style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px; border: 1px solid #dee2e6;">';
                    echo '<div style="font-size: 0.85rem; color: #6c757d;">📁 site.com</div>';
                    echo '<div style="display: flex; align-items: center; gap: 10px; margin: 5px 0;">';
                    echo '<span style="color: #dc3545;">tes.txt</span>';
                    echo '<span>→</span>';
                    echo '<span style="color: #28a745;">tes-baru.txt</span>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '<div style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px; border: 1px solid #dee2e6;">';
                    echo '<div style="font-size: 0.85rem; color: #6c757d;">📁 site2.com</div>';
                    echo '<div style="display: flex; align-items: center; gap: 10px; margin: 5px 0;">';
                    echo '<span style="color: #dc3545;">tes.txt</span>';
                    echo '<span>→</span>';
                    echo '<span style="color: #28a745;">tes-baru.txt</span>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <script>
        // Form submission loading
        document.getElementById('renameForm').addEventListener('submit', function() {
            document.getElementById('loader').style.display = 'block';
        });
        
        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('base_path').value === '') {
                document.getElementById('base_path').focus();
            }
        });
        
        // Auto-generate new filename suggestion
        document.getElementById('old_file_name').addEventListener('input', function() {
            const newFileNameInput = document.getElementById('new_file_name');
            if (newFileNameInput.value === '') {
                const oldName = this.value;
                if (oldName.includes('.')) {
                    const parts = oldName.split('.');
                    const ext = parts.pop();
                    const name = parts.join('.');
                    newFileNameInput.value = name + '-baru.' + ext;
                } else {
                    newFileNameInput.value = oldName + '-baru';
                }
            }
        });
    </script>
</body>
</html>
<?php
/**
 * UNLIMITED FILE RENAMER
 * Script untuk rename file secara massal dengan path lengkap
 * Tidak ada batasan tipe file - semua file bisa di-rename
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

class UnlimitedFileRenamer {
    private $processedFiles = [];
    private $errors = [];
    private $totalProcessed = 0;
    private $dryRun = true;
    
    /**
     * Rename single file with full path
     */
    public function renameSingleFile($oldFullPath, $newFullPath) {
        // Validate paths
        if (!file_exists($oldFullPath)) {
            $this->errors[] = "File tidak ditemukan: " . basename($oldFullPath);
            return false;
        }
        
        // Check if new path already exists
        if (file_exists($newFullPath)) {
            $this->errors[] = "File tujuan sudah ada: " . basename($newFullPath);
            return false;
        }
        
        // Get directory of new file
        $newDir = dirname($newFullPath);
        if (!is_dir($newDir) && !mkdir($newDir, 0755, true)) {
            $this->errors[] = "Gagal membuat direktori: " . $newDir;
            return false;
        }
        
        // Execute rename
        if (!$this->dryRun) {
            if (rename($oldFullPath, $newFullPath)) {
                $this->processedFiles[] = [
                    'old_path' => $oldFullPath,
                    'new_path' => $newFullPath,
                    'old_name' => basename($oldFullPath),
                    'new_name' => basename($newFullPath),
                    'status' => 'success',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $this->totalProcessed++;
                return true;
            } else {
                $this->errors[] = "Gagal rename: " . basename($oldFullPath) . " → " . basename($newFullPath);
                return false;
            }
        } else {
            // Dry run - preview only
            $this->processedFiles[] = [
                'old_path' => $oldFullPath,
                'new_path' => $newFullPath,
                'old_name' => basename($oldFullPath),
                'new_name' => basename($newFullPath),
                'status' => 'preview',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->totalProcessed++;
            return true;
        }
    }
    
    /**
     * Rename multiple files from array mapping
     */
    public function renameMultipleFiles($fileMappings) {
        foreach ($fileMappings as $oldPath => $newPath) {
            $this->renameSingleFile($oldPath, $newPath);
        }
    }
    
    /**
     * Rename files in directory with pattern
     */
    public function renameInDirectory($directory, $oldPattern, $newPattern, $useRegex = false) {
        if (!is_dir($directory)) {
            $this->errors[] = "Direktori tidak ditemukan: $directory";
            return;
        }
        
        $files = $this->getAllFiles($directory);
        
        foreach ($files as $oldFullPath) {
            $filename = basename($oldFullPath);
            $dirPath = dirname($oldFullPath);
            
            if ($useRegex) {
                $newFilename = preg_replace($oldPattern, $newPattern, $filename);
            } else {
                $newFilename = str_replace($oldPattern, $newPattern, $filename);
            }
            
            if ($newFilename !== $filename) {
                $newFullPath = $dirPath . '/' . $newFilename;
                $this->renameSingleFile($oldFullPath, $newFullPath);
            }
        }
    }
    
    /**
     * Rename files with sequential numbering
     */
    public function renameSequential($directory, $prefix = 'file_', $startNumber = 1, $digits = 3) {
        if (!is_dir($directory)) {
            $this->errors[] = "Direktori tidak ditemukan: $directory";
            return;
        }
        
        $files = $this->getAllFiles($directory);
        $counter = $startNumber;
        
        foreach ($files as $oldFullPath) {
            $extension = pathinfo($oldFullPath, PATHINFO_EXTENSION);
            $dirPath = dirname($oldFullPath);
            
            $newFilename = $prefix . str_pad($counter, $digits, '0', STR_PAD_LEFT);
            if ($extension) {
                $newFilename .= '.' . $extension;
            }
            
            $newFullPath = $dirPath . '/' . $newFilename;
            $this->renameSingleFile($oldFullPath, $newFullPath);
            $counter++;
        }
    }
    
    /**
     * Get all files recursively from directory
     */
    private function getAllFiles($dir, &$results = []) {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_file($path)) {
                $results[] = $path;
            } elseif (is_dir($path)) {
                $this->getAllFiles($path, $results);
            }
        }
        
        return $results;
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
            'total' => $this->totalProcessed,
            'mode' => $this->dryRun ? 'preview' : 'execute'
        ];
    }
    
    /**
     * Clear results
     */
    public function clearResults() {
        $this->processedFiles = [];
        $this->errors = [];
        $this->totalProcessed = 0;
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
    <title>UNLIMITED FILE RENAMER</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }
        
        @media (max-width: 1024px) {
            .content {
                grid-template-columns: 1fr;
            }
        }
        
        .form-section, .results-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border: 1px solid #e9ecef;
        }
        
        .section-title {
            color: #1e3c72;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            font-size: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            min-height: 150px;
            resize: vertical;
            font-family: monospace;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-option input[type="radio"] {
            width: auto;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-preview {
            background: #17a2b8;
            color: white;
        }
        
        .btn-preview:hover {
            background: #138496;
            transform: translateY(-2px);
        }
        
        .btn-execute {
            background: #28a745;
            color: white;
        }
        
        .btn-execute:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-clear {
            background: #6c757d;
            color: white;
        }
        
        .btn-clear:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-reset {
            background: #dc3545;
            color: white;
        }
        
        .btn-reset:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            max-height: 600px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .file-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .file-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .file-old {
            color: #dc3545;
            font-weight: 500;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .file-new {
            color: #28a745;
            font-weight: 500;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .file-path {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .file-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-preview {
            background: #ffc107;
            color: #856404;
        }
        
        .status-success {
            background: #28a745;
            color: white;
        }
        
        .status-failed {
            background: #dc3545;
            color: white;
        }
        
        .counter {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e3c72;
            margin: 20px 0;
            text-align: center;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
        
        .tab {
            padding: 10px 20px;
            background: #e9ecef;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: #1e3c72;
            color: white;
        }
        
        .tab:hover:not(.active) {
            background: #dee2e6;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .example {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        .example code {
            background: white;
            padding: 2px 5px;
            border-radius: 4px;
            font-family: monospace;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 UNLIMITED FILE RENAMER</h1>
            <p>Rename file secara massal dengan path lengkap • Tidak ada batasan tipe file</p>
        </div>
        
        <div class="content">
            <div class="form-section">
                <h2 class="section-title">📝 Konfigurasi Rename</h2>
                
                <div class="alert alert-warning">
                    ⚠️ <strong>PENTING:</strong> Selalu backup file Anda sebelum melakukan rename!
                </div>
                
                <form id="renameForm" method="post">
                    <div class="tabs">
                        <button type="button" class="tab active" data-tab="single">File Tunggal</button>
                        <button type="button" class="tab" data-tab="multiple">Multiple Files</button>
                        <button type="button" class="tab" data-tab="pattern">Pattern Rename</button>
                        <button type="button" class="tab" data-tab="sequential">Penomoran</button>
                    </div>
                    
                    <!-- Tab 1: Single File -->
                    <div id="single-tab" class="tab-content active">
                        <div class="form-group">
                            <label for="old_path_single">Path Lengkap File Lama:</label>
                            <input type="text" id="old_path_single" name="old_path_single" 
                                   placeholder="/home/username/file_lama.txt"
                                   value="<?php echo htmlspecialchars($_POST['old_path_single'] ?? ''); ?>">
                            <div class="example">
                                Contoh: <code>/home/euclair/tes.txt</code> atau <code>C:\Users\Nama\Documents\file_lama.doc</code>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_path_single">Path Lengkap File Baru:</label>
                            <input type="text" id="new_path_single" name="new_path_single" 
                                   placeholder="/home/username/file_baru.txt"
                                   value="<?php echo htmlspecialchars($_POST['new_path_single'] ?? ''); ?>">
                            <div class="example">
                                Contoh: <code>/home/euclair/tes-baru.txt</code> atau <code>C:\Users\Nama\Documents\file_baru.doc</code>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 2: Multiple Files -->
                    <div id="multiple-tab" class="tab-content">
                        <div class="form-group">
                            <label for="file_mappings">Mapping File (Format: old_path|new_path):</label>
                            <textarea id="file_mappings" name="file_mappings" 
                                      placeholder="/home/user/file1.txt|/home/user/new_file1.txt
/home/user/file2.jpg|/home/user/new_file2.jpg
/home/user/file3.pdf|/home/user/new_file3.pdf"><?php echo htmlspecialchars($_POST['file_mappings'] ?? ''); ?></textarea>
                            <div class="example">
                                Format: Satu mapping per baris, dipisahkan dengan tanda pipe (|)<br>
                                Contoh:<br>
                                <code>/home/euclair/tes.txt|/home/euclair/tes-baru.txt</code><br>
                                <code>/home/euclair/foto.jpg|/home/euclair/foto-lama.jpg</code>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 3: Pattern Rename -->
                    <div id="pattern-tab" class="tab-content">
                        <div class="form-group">
                            <label for="directory">Direktori:</label>
                            <input type="text" id="directory" name="directory" 
                                   placeholder="/home/username/documents"
                                   value="<?php echo htmlspecialchars($_POST['directory'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="old_pattern">Pattern Lama:</label>
                            <input type="text" id="old_pattern" name="old_pattern" 
                                   placeholder="file_"
                                   value="<?php echo htmlspecialchars($_POST['old_pattern'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_pattern">Pattern Baru:</label>
                            <input type="text" id="new_pattern" name="new_pattern" 
                                   placeholder="document_"
                                   value="<?php echo htmlspecialchars($_POST['new_pattern'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="pattern_type" value="simple" checked> 
                                    Simple Replace
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="pattern_type" value="regex"> 
                                    Regex Pattern
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 4: Sequential Rename -->
                    <div id="sequential-tab" class="tab-content">
                        <div class="form-group">
                            <label for="seq_directory">Direktori:</label>
                            <input type="text" id="seq_directory" name="seq_directory" 
                                   placeholder="/home/username/photos"
                                   value="<?php echo htmlspecialchars($_POST['seq_directory'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="prefix">Prefix:</label>
                            <input type="text" id="prefix" name="prefix" 
                                   placeholder="foto_"
                                   value="<?php echo htmlspecialchars($_POST['prefix'] ?? 'file_'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="start_number">Angka Mulai:</label>
                            <input type="number" id="start_number" name="start_number" 
                                   value="<?php echo $_POST['start_number'] ?? 1; ?>" min="1">
                        </div>
                        
                        <div class="form-group">
                            <label for="digits">Jumlah Digit:</label>
                            <input type="number" id="digits" name="digits" 
                                   value="<?php echo $_POST['digits'] ?? 3; ?>" min="1" max="10">
                        </div>
                    </div>
                    
                    <!-- Mode Selection -->
                    <div class="form-group">
                        <label>Mode:</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="mode" value="preview" checked> 
                                🔍 Preview (Tampilkan perubahan saja)
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="mode" value="execute"> 
                                🚀 Execute (Lakukan rename sebenarnya)
                            </label>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="btn-group">
                        <button type="submit" name="action" value="preview" class="btn btn-preview">
                            🔍 Preview Perubahan
                        </button>
                        <button type="submit" name="action" value="execute" class="btn btn-execute" 
                                onclick="return confirm('⚠️ YAKIN ingin melakukan rename? Pastikan sudah backup data!')">
                            🚀 Execute Rename
                        </button>
                        <button type="button" class="btn btn-clear" onclick="clearForm()">
                            🗑️ Clear Form
                        </button>
                        <button type="reset" class="btn btn-reset">
                            🔄 Reset
                        </button>
                    </div>
                </form>
                
                <div class="loader" id="loader">
                    <div class="spinner"></div>
                    <p>Memproses file...</p>
                </div>
            </div>
            
            <div class="results-section">
                <h2 class="section-title">📊 Hasil Proses</h2>
                
                <?php
                // Proses form submission
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $renamer = new UnlimitedFileRenamer();
                    $activeTab = $_POST['active_tab'] ?? 'single';
                    
                    // Set mode
                    $isExecute = ($_POST['action'] ?? '') === 'execute';
                    $renamer->setDryRun(!$isExecute);
                    
                    try {
                        // Process based on active tab
                        switch ($activeTab) {
                            case 'single':
                                if (!empty($_POST['old_path_single']) && !empty($_POST['new_path_single'])) {
                                    $renamer->renameSingleFile(
                                        $_POST['old_path_single'],
                                        $_POST['new_path_single']
                                    );
                                }
                                break;
                                
                            case 'multiple':
                                if (!empty($_POST['file_mappings'])) {
                                    $mappings = [];
                                    $lines = explode("\n", $_POST['file_mappings']);
                                    foreach ($lines as $line) {
                                        $line = trim($line);
                                        if (empty($line)) continue;
                                        
                                        $parts = explode('|', $line, 2);
                                        if (count($parts) === 2) {
                                            $mappings[trim($parts[0])] = trim($parts[1]);
                                        }
                                    }
                                    
                                    if (!empty($mappings)) {
                                        $renamer->renameMultipleFiles($mappings);
                                    }
                                }
                                break;
                                
                            case 'pattern':
                                if (!empty($_POST['directory']) && !empty($_POST['old_pattern'])) {
                                    $useRegex = ($_POST['pattern_type'] ?? 'simple') === 'regex';
                                    $renamer->renameInDirectory(
                                        $_POST['directory'],
                                        $_POST['old_pattern'],
                                        $_POST['new_pattern'] ?? '',
                                        $useRegex
                                    );
                                }
                                break;
                                
                            case 'sequential':
                                if (!empty($_POST['seq_directory'])) {
                                    $renamer->renameSequential(
                                        $_POST['seq_directory'],
                                        $_POST['prefix'] ?? 'file_',
                                        $_POST['start_number'] ?? 1,
                                        $_POST['digits'] ?? 3
                                    );
                                }
                                break;
                        }
                        
                        // Get results
                        $results = $renamer->getResults();
                        
                        // Display mode info
                        if ($isExecute) {
                            echo '<div class="alert alert-success">';
                            echo '✅ <strong>EXECUTE MODE:</strong> Rename berhasil dieksekusi!';
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-info">';
                            echo '🔍 <strong>PREVIEW MODE:</strong> Tidak ada perubahan yang dilakukan (hanya preview)';
                            echo '</div>';
                        }
                        
                        // Display errors
                        if (!empty($results['errors'])) {
                            echo '<div class="alert alert-danger">';
                            echo '❌ <strong>Error (' . count($results['errors']) . '):</strong>';
                            echo '<ul style="margin-top: 10px; margin-left: 20px;">';
                            foreach ($results['errors'] as $error) {
                                echo '<li>' . htmlspecialchars($error) . '</li>';
                            }
                            echo '</ul>';
                            echo '</div>';
                        }
                        
                        // Display results
                        if (!empty($results['processed'])) {
                            echo '<div class="counter">';
                            echo '📁 Total File Diproses: ' . $results['total'];
                            echo '</div>';
                            
                            echo '<div class="results-container">';
                            foreach ($results['processed'] as $index => $file) {
                                echo '<div class="file-item">';
                                echo '<div class="file-path">';
                                echo 'Path: ' . htmlspecialchars(dirname($file['old_path']));
                                echo '</div>';
                                echo '<div class="file-old">';
                                echo '➡️ ' . htmlspecialchars($file['old_name']);
                                echo '</div>';
                                echo '<div class="file-new">';
                                echo '✅ ' . htmlspecialchars($file['new_name']);
                                echo '</div>';
                                echo '<div>';
                                echo '<span class="file-status status-' . $file['status'] . '">';
                                echo $file['status'] === 'success' ? '✓ Berhasil' : '👁️ Preview';
                                echo '</span>';
                                echo ' <small>' . $file['timestamp'] . '</small>';
                                echo '</div>';
                                echo '</div>';
                            }
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-info">';
                            echo 'ℹ️ Tidak ada file yang diproses. Periksa konfigurasi Anda.';
                            echo '</div>';
                        }
                        
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">';
                        echo '❌ <strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert alert-info">';
                    echo '👈 Konfigurasikan rename di panel kiri, lalu klik Preview atau Execute';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabId + '-tab').classList.add('active');
                
                // Update hidden field for active tab
                document.getElementById('activeTabField').value = tabId;
            });
        });
        
        // Form submission loading
        document.getElementById('renameForm').addEventListener('submit', function() {
            document.getElementById('loader').style.display = 'block';
        });
        
        // Clear form function
        function clearForm() {
            if (confirm('Yakin ingin membersihkan form?')) {
                document.getElementById('renameForm').reset();
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById('single-tab').classList.add('active');
                document.querySelectorAll('.tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelector('[data-tab="single"]').classList.add('active');
            }
        }
        
        // Add hidden field for active tab
        const form = document.getElementById('renameForm');
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = 'active_tab';
        hiddenField.id = 'activeTabField';
        hiddenField.value = 'single';
        form.appendChild(hiddenField);
        
        // Update active tab on form submission
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.getElementById('activeTabField').value = this.getAttribute('data-tab');
            });
        });
    </script>
</body>
</html>
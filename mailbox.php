<?php
/**
 * Application: Laragon | Server Index Inbox Page
 * Description: This is the main index page for the Laragon server, displaying server info and applications.
 * Author: Tarek Tarabichi <tarek@2tinteractive.com>
 * Contributors: LrkDev in v.2.3.3
 * Version: 2.3.3
 */

const EML_FILE_PATH = __DIR__ . '/../bin/sendmail/output/';

// Functions from first file (email parsing and rendering)
function findEmailFile($filename)
{
    $filePath = rtrim(EML_FILE_PATH, '/') . '/' . basename($filename);
    if (file_exists($filePath)) {
        return $filePath;
    }
    return false;
}

function parseEmail($content)
{
    $parts = preg_split("/\r?\n\r?\n/", $content, 2);
    $headers = isset($parts[0]) ? $parts[0] : '';
    $body = isset($parts[1]) ? $parts[1] : '';

    $parsed = [
        'headers' => parseHeaders($headers),
        'body' => $body
    ];

    if (isset($parsed['headers']['Content-Type']) && strpos($parsed['headers']['Content-Type'], 'multipart/') !== false) {
        $parsed['parts'] = parseMultipart($body, $parsed['headers']['Content-Type']);
    }

    return $parsed;
}

function parseHeaders($headers)
{
    $parsed = [];
    $headers = explode("\n", $headers);
    foreach ($headers as $header) {
        if (preg_match('/^([^:]+):\s*(.+)$/', $header, $matches)) {
            $parsed[$matches[1]] = $matches[2];
        }
    }
    return $parsed;
}

function parseMultipart($body, $contentType)
{
    if (preg_match('/boundary="?(.+?)"?$/i', $contentType, $matches)) {
        $boundary = $matches[1];
        $parts = preg_split("/--" . preg_quote($boundary) . "(?=\s|$)/", $body);
        array_pop($parts);
        array_shift($parts);
        return array_map('parseEmailPart', $parts);
    }
    return [];
}

function parseEmailPart($part)
{
    $parsed = parseEmail($part);
    $parsed['content'] = isset($parsed['headers']['Content-Transfer-Encoding']) ?
        decodeContent($parsed['body'], $parsed['headers']['Content-Transfer-Encoding']) :
        $parsed['body'];
    return $parsed;
}

function decodeContent($content, $encoding)
{
    switch (strtolower($encoding)) {
        case 'base64':
            return base64_decode($content);
        case 'quoted-printable':
            return quoted_printable_decode($content);
        default:
            return $content;
    }
}

function renderEmail($parsed)
{
    $output = '<div class="email-headers">';
    foreach ($parsed['headers'] as $key => $value) {
        $output .= "<strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "<br>";
    }
    $output .= '</div><hr>';

    if (isset($parsed['parts'])) {
        foreach ($parsed['parts'] as $part) {
            if (isset($part['headers']['Content-Type'])) {
                if (strpos($part['headers']['Content-Type'], 'text/plain') !== false) {
                    $output .= '<pre>' . htmlspecialchars($part['content']) . '</pre>';
                } elseif (strpos($part['headers']['Content-Type'], 'text/html') !== false) {
                    $output .= $part['content'];
                }
            }
        }
    } else {
        $output .= '<pre>' . htmlspecialchars($parsed['body']) . '</pre>';
    }

    return $output;
}

// Functions from second file (email list management)
function getEmailMetadata($filename)
{
    $content = file_get_contents(EML_FILE_PATH . $filename);
    $subject = preg_match('/Subject: (.*)/', $content, $matches) ? $matches[1] : 'No Subject';
    $from = preg_match('/From: (.*)/', $content, $matches) ? $matches[1] : 'Unknown Sender';
    $date = preg_match('/Date: (.*)/', $content, $matches) ? strtotime($matches[1]) : 0;
    return ['subject' => $subject, 'from' => $from, 'date' => $date];
}

function handleEmailDeletion($directory)
{
    if (isset($_GET['delete'])) {
        $fileToDelete = $directory . basename($_GET['delete']);
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

function getEmails($directory)
{
    if (!is_dir($directory)) {
        echo "<p>Directory does not exist: $directory</p>";
        return [];
    }
    $files = scandir($directory);
    if ($files === false) {
        echo "<p>Failed to scan directory: $directory</p>";
        return [];
    }
    $files = array_diff($files, ['.', '..']);
    $emails = array_filter($files, function ($file) {
        return preg_match('~^mail-\d{8}-\d{6}\.\d{3}\.txt$~', $file);
    });

    $emailsWithMetadata = [];
    foreach ($emails as $email) {
        $metadata = getEmailMetadata($email);
        $emailsWithMetadata[] = array_merge(['filename' => $email], $metadata);
    }
    return $emailsWithMetadata;
}

function sortEmails($emails, $sortBy = 'date', $sortOrder = SORT_DESC)
{
    $sortArray = array();
    foreach ($emails as $key => $email) {
        $sortArray[$key] = $email[$sortBy];
    }
    array_multisort($sortArray, $sortOrder, $emails);
    return $emails;
}

// Handle AJAX request for email content
if (isset($_GET['email']) && !isset($_GET['delete']) && !isset($_GET['sort'])) {
    $emailFile = findEmailFile($_GET['email']);
    if ($emailFile) {
        $content = file_get_contents($emailFile);
        if ($content === false) {
            echo "Error reading email file.";
        } else {
            $parsed = parseEmail($content);
            echo renderEmail($parsed);
        }
    } else {
        echo "Email not found.";
    }
    exit;
}

// Main inbox processing
handleEmailDeletion(EML_FILE_PATH);
$emails = getEmails(EML_FILE_PATH);
$sortBy = $_GET['sort'] ?? 'date';
$sortOrder = $_GET['order'] ?? SORT_DESC;
$emails = sortEmails($emails, $sortBy, $sortOrder);

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laragon Mailbox</title>
	<!-- Có thể xóa file build.css bên trên và sử dụng bằng CDN: https://cdn.tailwindcss.com/3.4.16 -->
    <link rel="stylesheet" href="./build.css">
    <!-- <link rel="stylesheet" href="https://cdn.tailwindcss.com/3.4.16"> -->
    <link rel="icon" sizes="any" type="image/svg+xml"
        href='data:image/svg+xml,%3Csvg%20viewBox%3D%22-58.56999999999999%20-59.93000000000002%20908.27%20797.3599999999999%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22227.0208%22%3E%3ClinearGradient%20id%3D%22a%22%20gradientUnits%3D%22userSpaceOnUse%22%20x1%3D%22400.117%22%20x2%3D%22400.117%22%20y1%3D%2222.293%22%20y2%3D%22715%22%3E%3Cstop%20offset%3D%22.018%22%20stop-color%3D%22%233bb6ff%22%2F%3E%3Cstop%20offset%3D%22.302%22%20stop-color%3D%22%2339afff%22%2F%3E%3Cstop%20offset%3D%22.552%22%20stop-color%3D%22%2336a3ff%22%2F%3E%3Cstop%20offset%3D%22.717%22%20stop-color%3D%22%23359fff%22%2F%3E%3Cstop%20offset%3D%22.832%22%20stop-color%3D%22%233398ff%22%2F%3E%3Cstop%20offset%3D%22.964%22%20stop-color%3D%22%233297ff%22%2F%3E%3C%2FlinearGradient%3E%3Cpath%20d%3D%22M25.27%20252.7c1.36-4.1%2058.96-201.67%20287.1-204.43%200%200%2095.66-108.2%20226%200%200%200%2035.36%2029.06%2054.76%2089.4%200%200%20171.14%2025.96%20198.84%20167.4%200%200%2057.73%20232.9-137.77%20396.53%200%200-27.53%2022.03-45.87%2032.27%200%200-40.66.06-49.06%200-17.9-.14-29.2%200-45.47%200%200%200-25-8.94-26.03-37.5%200%200-2.1-99.34-1.54-116.5%200%200%20.5-16.07-22.9-15.07%200%200-22.33-2.57-25.5%2016.63%200%200-.53%20102.47-1.03%20120.64%200%200-1.57%2030.23-35.37%2031.7%200%200-121.16%203.66-137.26-2.07%200%200-28.07-5.2-30.17-31.73%200%200-22.9-135.2-27.03-177.27%200%200-76.97-42.67-92.57-54.1%200%200%205.2%20137.77%2053.03%20196.03%200%200%208.34%207.3-8.33%2017.67%200%200-6.23%205.2-12.5%202.13%200%200-205.17-114.6-129.6-407.1%22%20fill%3D%22url%28%23a%29%22%2F%3E%3Cpath%20d%3D%22M254.93%20441.17s179%20102.03%20287.3-61.77c0%200%2087.7-114.53%2052.77-236.7%200%200%2061.5%20102.67-57.5%20261.97.03.03-100.9%20142.03-282.57%2036.5z%22%20fill%3D%22%23069%22%2F%3E%3Cpath%20d%3D%22M184.1%20417.1s12.77%2059.1-26.5%2077.7c0%200-89.33-36.8-80.3-104.77%200%200%202.8-16.96%2019.43-6.56%200%200%2039.54%2021.5%2070.74%2027.7-.04%200%2015.83%201.46%2016.63%205.93z%22%20fill%3D%22%23cee6ff%22%2F%3E%3Cpath%20d%3D%22M159.3%20317.2s13.47-57.57%2064.3-53.93c0%200%2043.2%201.16%2044.73%2060.56%200%20.04-34.03-88.83-109.03-6.63z%22%20fill%3D%22%23069%22%2F%3E%3C%2Fsvg%3E'>

    <style>
        #emailModal {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0s linear 0.3s;
        }

        #emailModal.show {
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s ease, visibility 0s linear 0s;
        }

        #emailModal .modal-content {
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        #emailModal.show .modal-content {
            transform: scale(1);
        }

        body.modal-open {
            overflow: hidden;
        }
    </style>
</head>

<body>
    <div class="min-h-screen flex flex-col">
        <main class="flex-grow">
            <div class="max-w-6xl mx-auto mt-10 bg-gray-50 border border-gray-300 p-5 rounded-md text-black">
                <h1 class="text-2xl font-bold text-center text-black">
                    <?php echo $translations['email-list'] ?? 'Laragon Server Inbox'; ?>
                </h1>

                <div class="mt-4">
                    <input type="text" id="emailSearchInput"
                        class="w-full p-2 border rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Search emails...">
                </div>

                <?php if (empty($emails)): ?>
                    <div class="mt-4 p-4 bg-red-100 text-red-800 rounded-md">
                        <?php echo $translations['no-emails-found'] ?? 'No Emails Found'; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-6 mb-16 space-y-4">
                        <?php foreach ($emails as $email): ?>
                            <div
                                class="email-item border border-gray-300 rounded-md p-3 flex justify-between items-center hover:bg-gray-200 cursor-pointer">
                                <div class="email-content flex-grow" data-email="<?= htmlspecialchars($email['filename']) ?>">
                                    <div class="email-sender font-semibold"><?= htmlspecialchars($email['from']) ?></div>
                                    <div class="email-subject text-gray-700"><?= htmlspecialchars($email['subject']) ?></div>
                                    <div class="email-date text-sm text-gray-500"><?= date('Y-m-d H:i:s', $email['date']) ?>
                                    </div>
                                </div>
                                <button
                                    class="btn btn-sm bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 email-delete"
                                    data-email="<?= htmlspecialchars($email['filename']) ?>">Delete</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="fixed inset-0 bg-black bg-opacity-50" id="emailModal" tabindex="-1" role="dialog">
                <div class="max-w-4xl mx-auto mt-20 bg-white rounded-lg shadow-lg p-6 modal-content">
                    <div class="flex justify-between items-center">
                        <h5 class="text-xl font-semibold">Email Content</h5>
                        <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body mt-4 max-h-[60vh] overflow-y-auto border border-gray-300 rounded-lg p-4">
                    </div>
                    <div class="mt-4 flex justify-end space-x-2">
                        <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700" id="modalDeleteButton">Delete</button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-8 text-center font-bold">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
				<p class="text-center text-sm text-gray-500">Developed by HungNth</p>
                <p class="text-center text-sm text-gray-500">
                    Powered by Laragon - <?= date('Y'); ?>
                </p>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let currentEmail;
            const modal = document.getElementById('emailModal');
            const body = document.body;

            // Handle email content click
            document.querySelectorAll('.email-content').forEach(content => {
                content.addEventListener('click', function () {
                    const email = this.dataset.email;
                    currentEmail = email;

                    fetch('<?php echo $_SERVER['PHP_SELF']; ?>?email=' + encodeURIComponent(email))
                        .then(response => response.text())
                        .then(data => {
                            document.querySelector('#emailModal .modal-body').innerHTML = data;
                            modal.classList.add('show');
                            body.classList.add('modal-open');
                        });
                });
            });

            // Handle email deletion
            function handleDelete(email) {
                if (confirm('Are you sure you want to delete this email?')) {
                    fetch('<?php echo $_SERVER['PHP_SELF']; ?>?delete=' + encodeURIComponent(email))
                        .then(() => {
                            location.reload();
                        });
                }
            }

            document.querySelectorAll('.email-delete').forEach(button => {
                button.addEventListener('click', function (e) {
                    e.stopPropagation();
                    handleDelete(this.dataset.email);
                });
            });

            document.getElementById('modalDeleteButton')?.addEventListener('click', function (e) {
                e.stopPropagation();
                if (currentEmail) {
                    handleDelete(currentEmail);
                }
            });

            // Search
            document.getElementById('emailSearchInput')?.addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase();
                document.querySelectorAll('.email-item').forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });

            // Handle modal close
            document.addEventListener('click', function (event) {
                const modal = document.getElementById('emailModal');
                if (event.target === modal || event.target.hasAttribute('data-bs-dismiss')) {
                    modal.classList.remove('show');
                    body.classList.remove('modal-open');
                }
            });
        });
    </script>
</body>

</html>
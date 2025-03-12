<?php

// Security: Disable directory listing for safety
if (!defined('ALLOWED_ACCESS')) {
    define('ALLOWED_ACCESS', true);
}

// Configuration
$config = [
    'app_name' => 'Laragon',
    'github_url' => 'https://github.com/Hungnth/laragon-dashboard',
    'excluded_folders' => ['.', '..', '.git', '.svn', '.htaccess', '.idea', '__pycache__', '.venv', 'assets'],
    'db_config' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
    ],
    'domains_subfix' => '.test'
];

function handleQueryParameter(string $param): void
{
    if ($param === 'info') {
        phpinfo();
    }
    if ($param === 'mailbox') {
        include 'mailbox.php';
    }
}

if (isset($_GET['q'])) {
    $queryParam = htmlspecialchars(filter_input(INPUT_GET, 'q', FILTER_DEFAULT)) ?: null;
    try {
        handleQueryParameter($queryParam);
    } catch (InvalidArgumentException $e) {
        echo 'Error: ' . htmlspecialchars($e->getMessage());
    }
}

// Get MySQL databases
function getDatabases($config)
{
    $databases = [];
    try {
        $mysqli = new mysqli($config['host'], $config['user'], $config['password']);
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }

        // Get databases
        $result = $mysqli->query("SHOW DATABASES");
        while ($row = $result->fetch_array()) {
            if (!in_array($row[0], ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
                $dbName = $row[0];
                // Get tables count for each database
                $mysqli->select_db($dbName);
                $tablesResult = $mysqli->query("SHOW TABLES");
                $databases[$dbName] = [
                    'name' => $dbName,
                    'tables' => $tablesResult->num_rows,
                    'size' => 0
                ];

                // Calculate database size
                $sizeResult = $mysqli->query("SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size 
                    FROM information_schema.tables 
                    WHERE table_schema = '$dbName'");
                $sizeRow = $sizeResult->fetch_assoc();
                $databases[$dbName]['size'] = $sizeRow['size'] ?? 0;
            }
        }
        $mysqli->close();
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
    }
    return $databases;
}

$link = mysqli_connect('localhost', 'root', '');
$sql_version = mysqli_get_server_info($link);

// Get system information
function getSystemInfo()
{
    global $sql_version;
    return [
        'PHP Version' => phpversion(),
        'My SQL Version' => $sql_version,
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'Server OS' => PHP_OS,
        'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
        'Max Upload Size' => ini_get('upload_max_filesize'),
        'Max Post Size' => ini_get('post_max_size'),
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time') . 's',
        'PHP Modules' => count(get_loaded_extensions()) . ' loaded',
    ];
}

// Get directories with error handling
function getProjectFolders($excluded = [])
{
    try {
        $folders = array_filter(glob('*'), 'is_dir');
        return array_values(array_diff($folders, $excluded));
    } catch (Exception $e) {
        error_log("Error reading directories: " . $e->getMessage());
        return [];
    }
}

function getSites($folders, $config)
{

    $sites = [];

    foreach ($folders as $folder) {
        $site = ['name' => $folder];

        if (file_exists("$folder/wp-admin")) {
            $site['framework'] = 'WordPress';
            $site['logo'] = '<svg viewBox="0 0 122.52 122.523" width="24" height="24" xmlns="http://www.w3.org/2000/svg"><g fill="#21759b"><path d="m8.708 61.26c0 20.802 12.089 38.779 29.619 47.298l-25.069-68.686c-2.916 6.536-4.55 13.769-4.55 21.388z"/><path d="m96.74 58.608c0-6.495-2.333-10.993-4.334-14.494-2.664-4.329-5.161-7.995-5.161-12.324 0-4.831 3.664-9.328 8.825-9.328.233 0 .454.029.681.042-9.35-8.566-21.807-13.796-35.489-13.796-18.36 0-34.513 9.42-43.91 23.688 1.233.037 2.395.063 3.382.063 5.497 0 14.006-.667 14.006-.667 2.833-.167 3.167 3.994.337 4.329 0 0-2.847.335-6.015.501l19.138 56.925 11.501-34.493-8.188-22.434c-2.83-.166-5.511-.501-5.511-.501-2.832-.166-2.5-4.496.332-4.329 0 0 8.679.667 13.843.667 5.496 0 14.006-.667 14.006-.667 2.835-.167 3.168 3.994.337 4.329 0 0-2.853.335-6.015.501l18.992 56.494 5.242-17.517c2.272-7.269 4.001-12.49 4.001-16.989z"/><path d="m62.184 65.857-15.768 45.819c4.708 1.384 9.687 2.141 14.846 2.141 6.12 0 11.989-1.058 17.452-2.979-.141-.225-.269-.464-.374-.724z"/><path d="m107.376 36.046c.226 1.674.354 3.471.354 5.404 0 5.333-.996 11.328-3.996 18.824l-16.053 46.413c15.624-9.111 26.133-26.038 26.133-45.426.001-9.137-2.333-17.729-6.438-25.215z"/><path d="m61.262 0c-33.779 0-61.262 27.481-61.262 61.26 0 33.783 27.483 61.263 61.262 61.263 33.778 0 61.265-27.48 61.265-61.263-.001-33.779-27.487-61.26-61.265-61.26zm0 119.715c-32.23 0-58.453-26.223-58.453-58.455 0-32.23 26.222-58.451 58.453-58.451 32.229 0 58.45 26.221 58.45 58.451 0 32.232-26.221 58.455-58.45 58.455z"/></g></svg>';
            $site['url'] = 'https://' . $folder . $config['domains_subfix'] . '/wp-admin';
        } elseif (file_exists("$folder/public/index.php") && is_dir("$folder/app") && file_exists("$folder/.env")) {
            $site['framework'] = 'Laravel';
            $site['logo'] = '<svg xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid" viewBox="0 0 256 264" height="24" width="24"><path d="m255.9 59.6.1 1.1v56.6c0 1.4-.8 2.8-2 3.5l-47.6 27.4v54.2c0 1.4-.7 2.8-2 3.5l-99.1 57-.7.4-.3.1c-.7.2-1.4.2-2.1 0l-.4-.1-.6-.3L2 206c-1.3-.8-2.1-2.2-2.1-3.6V32.7l.1-1.1.2-.4.3-.6.2-.4.4-.5.4-.3c.2 0 .3-.2.5-.3L51.6.6c1.3-.8 2.9-.8 4.1 0L105.3 29c.2 0 .3.2.4.3l.5.3c0 .2.2.4.3.5l.3.4.3.6.1.4.2 1v106l41.2-23.7V60.7c0-.4 0-.7.2-1l.1-.4.3-.7.3-.3.3-.5.5-.3.4-.4 49.6-28.5c1.2-.7 2.8-.7 4 0L254 57l.5.4.4.3.4.5.2.3c.2.2.2.5.3.7l.2.3Zm-8.2 55.3v-47l-17.3 10-24 13.7v47l41.3-23.7Zm-49.5 85v-47l-23.6 13.5-67.2 38.4v47.5l90.8-52.3ZM8.2 39.9V200l90.9 52.3v-47.5l-47.5-26.9-.4-.4c-.2 0-.3-.1-.4-.3l-.4-.4-.3-.4-.2-.5-.2-.5v-.6l-.2-.5V63.6L25.6 49.8l-17.3-10Zm45.5-31L12.4 32.8l41.3 23.7 41.2-23.7L53.7 8.9ZM75 157.3l24-13.8V39.8l-17.3 10-24 13.8v103.6l17.3-10ZM202.3 36.9 161 60.7l41.3 23.8 41.3-23.8-41.3-23.8Zm-4.1 54.7-24-13.8-17.3-10v47l24 13.9 17.3 10v-47Zm-95 106 60.6-34.5 30.2-17.3-41.2-23.8-47.5 27.4L62 174.3l41.2 23.3Z" fill="#FF2D20"/></svg>';
            $site['url'] = 'https://' . $folder . $config['domains_subfix'];
        } elseif (file_exists("$folder/") && file_exists("$folder/app.py") && is_dir("$folder/static") && file_exists("$folder/.env")) {
            $site['framework'] = 'Python';
            $site['logo'] = '<svg id="fi_5968350" enable-background="new 0 0 512 512" height="24" viewBox="0 0 512 512" width="24" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><linearGradient id="path1948_00000153663380415884157280000008978240784360615575_" gradientTransform="matrix(.563 0 0 -.568 -514.163 286.842)" gradientUnits="userSpaceOnUse" x1="896.959" x2="1393.622" y1="479.369" y2="52.058"><stop offset="0" stop-color="#5a9fd4"></stop><stop offset="1" stop-color="#306998"></stop></linearGradient><linearGradient id="path1950_00000075154291388394646000000000998535923326963881_" gradientTransform="matrix(.563 0 0 -.568 -514.163 286.842)" gradientUnits="userSpaceOnUse" x1="1585.627" x2="1408.278" y1="-206.531" y2="44.386"><stop offset="0" stop-color="#ffd43b"></stop><stop offset="1" stop-color="#ffe873"></stop></linearGradient><g><g id="g2303"><path id="path1948" d="m252.985.004c-20.882.097-40.823 1.878-58.369 4.983-51.689 9.132-61.074 28.245-61.074 63.494v46.553h122.147v15.517h-122.147-45.841c-35.499 0-66.584 21.337-76.306 61.928-11.215 46.526-11.712 75.56 0 124.141 8.683 36.161 29.418 61.927 64.917 61.927h41.997v-55.807c0-40.317 34.883-75.879 76.307-75.879h122.005c33.962 0 61.073-27.963 61.073-62.07v-116.31c0-33.102-27.926-57.969-61.073-63.494-20.983-3.493-42.755-5.08-63.636-4.983zm-66.057 37.442c12.617 0 22.92 10.472 22.92 23.347 0 12.83-10.303 23.205-22.92 23.205-12.662 0-22.92-10.375-22.92-23.205 0-12.876 10.258-23.347 22.92-23.347z" fill="url(#path1948_00000153663380415884157280000008978240784360615575_)"></path><path id="path1950" d="m392.927 130.551v54.24c0 42.052-35.652 77.445-76.306 77.445h-122.005c-33.419 0-61.074 28.602-61.074 62.07v116.31c0 33.102 28.785 52.573 61.074 62.07 38.665 11.369 75.743 13.424 122.005 0 30.751-8.903 61.073-26.821 61.073-62.07v-46.553h-122.005v-15.518h122.005 61.074c35.499 0 48.728-24.761 61.074-61.927 12.753-38.262 12.21-75.057 0-124.141-8.773-35.34-25.529-61.928-61.074-61.928h-45.841zm-68.618 294.548c12.662 0 22.92 10.375 22.92 23.205 0 12.876-10.258 23.348-22.92 23.348-12.617 0-22.92-10.472-22.92-23.348-.001-12.83 10.302-23.205 22.92-23.205z" fill="url(#path1950_00000075154291388394646000000000998535923326963881_)"></path></g></g></svg>';
            $site['url'] = 'https://' . $folder . $config['domains_subfix'];
        } else {
            $site['framework'] = 'Unknown';
            $site['logo'] = '<svg id="fi_4314762" enable-background="new 0 0 512 512" height="24" viewBox="0 0 512 512" width="24" xmlns="http://www.w3.org/2000/svg"><circle cx="256" cy="256" fill="#4caf50" r="256"></circle><path d="m256 81.5c-96.22 0-174.5 78.28-174.5 174.5 0 27.801 6.546 54.098 18.161 77.448.012.03.019.06.031.09.251.63.56 1.218.9 1.779 28.93 56.457 87.731 95.183 155.408 95.183 67.645 0 126.423-38.69 155.369-95.103.359-.583.677-1.201.939-1.858.011-.028.018-.057.029-.086 11.616-23.351 18.163-49.65 18.163-77.453 0-96.22-78.28-174.5-174.5-174.5zm138.896 232.435c-18.75-6.709-38.786-11.994-59.732-15.752 1.506-13.634 2.285-27.778 2.285-42.182 0-15.293-.876-30.295-2.57-44.701 20.692-3.698 40.504-8.879 59.062-15.454 8.07 18.434 12.56 38.778 12.56 60.154-.001 20.521-4.135 40.093-11.605 57.935zm-138.896 92.565c-13.053 0-27.286-15.07-38.074-40.311-5.958-13.941-10.637-30.137-13.905-47.798 16.896-2.032 34.287-3.092 51.979-3.092s35.083 1.06 51.978 3.092c-3.268 17.661-7.946 33.857-13.905 47.798-10.787 25.242-25.02 40.311-38.073 40.311zm0-115.201c-18.843 0-37.388 1.144-55.424 3.34-1.336-12.459-2.026-25.414-2.026-38.638 0-14.123.788-27.937 2.308-41.169 17.952 2.174 36.404 3.307 55.142 3.307s37.189-1.133 55.141-3.306c1.521 13.232 2.308 27.046 2.308 41.169 0 13.225-.69 26.179-2.026 38.638-18.035-2.197-36.58-3.341-55.423-3.341zm0-185.799c13.053 0 27.286 15.069 38.073 40.311 5.675 13.279 10.188 28.604 13.428 45.291-16.75 1.995-33.981 3.036-51.501 3.036s-34.751-1.041-51.502-3.036c3.24-16.687 7.752-32.012 13.428-45.291 10.788-25.241 25.021-40.311 38.074-40.311zm126.428 68.94c-16.145 5.507-33.296 9.902-51.181 13.127-3.584-18.794-8.667-36.125-15.104-51.188-3.695-8.646-7.765-16.336-12.146-23.031 32.52 10.971 60.098 32.773 78.431 61.092zm-174.425-61.091c-4.382 6.695-8.451 14.385-12.146 23.031-6.437 15.063-11.52 32.394-15.104 51.188-17.885-3.225-35.036-7.62-51.181-13.127 18.333-28.32 45.91-50.121 78.431-61.092zm-89.943 82.497c18.558 6.574 38.369 11.756 59.061 15.453-1.694 14.406-2.571 29.408-2.571 44.701 0 14.404.78 28.549 2.285 42.182-20.945 3.759-40.98 9.043-59.731 15.752-7.47-17.841-11.604-37.413-11.604-57.934 0-21.376 4.489-41.72 12.56-60.154zm10.182 139.612c16.396-5.677 33.84-10.202 52.053-13.508 3.604 19.75 8.842 37.949 15.562 53.671 3.695 8.646 7.765 16.335 12.146 23.031-33.319-11.241-61.446-33.853-79.761-63.194zm175.754 63.194c4.382-6.696 8.451-14.385 12.146-23.031 6.72-15.723 11.958-33.921 15.562-53.672 18.213 3.307 35.658 7.832 52.053 13.509-18.314 29.341-46.442 51.954-79.761 63.194z" fill="#fff"></path></svg>';
            $site['url'] = 'https://' . $folder . $config['domains_subfix'];
        }

        $sites[] = $site;
    }

    return $sites;
}


$folders = getProjectFolders($config['excluded_folders']);
$sites = getSites($folders, $config);
$databases = getDatabases($config['db_config']);
$systemInfo = getSystemInfo();

// Get PHP Extensions
$extensions = get_loaded_extensions();
sort($extensions);

function dd($var)
{
    echo "<pre>";
    print_r($var);
    exit;
}

// Get system resource usage
$systemResources = [
    'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2),
    'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2),
    'cpu_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : ['N/A'],
    'disk_free' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2),
    'disk_total' => round(disk_total_space('/') / 1024 / 1024 / 1024, 2)
];

// Add to systemInfo array
$systemInfo['Memory Usage'] = $systemResources['memory_usage'] . ' MB';
$systemInfo['Peak Memory'] = $systemResources['peak_memory'] . ' MB';
$systemInfo['CPU Load'] = is_array($systemResources['cpu_load']) ? implode(', ', $systemResources['cpu_load']) : $systemResources['cpu_load'];
$systemInfo['Disk Free Space'] = $systemResources['disk_free'] . ' GB';
$systemInfo['Disk Total Space'] = $systemResources['disk_total'] . ' GB';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laragon Dashboard</title>
	<!-- Có thể xóa file build.css bên trên và sử dụng bằng CDN: https://cdn.tailwindcss.com/3.4.16 -->
    <link rel="stylesheet" href="./build.css">
    <!-- <link rel="stylesheet" href="https://cdn.tailwindcss.com/3.4.16"> -->
    <link rel="icon" sizes="any" type="image/svg+xml"
        href='data:image/svg+xml,%3Csvg%20viewBox%3D%22-58.56999999999999%20-59.93000000000002%20908.27%20797.3599999999999%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22227.0208%22%3E%3ClinearGradient%20id%3D%22a%22%20gradientUnits%3D%22userSpaceOnUse%22%20x1%3D%22400.117%22%20x2%3D%22400.117%22%20y1%3D%2222.293%22%20y2%3D%22715%22%3E%3Cstop%20offset%3D%22.018%22%20stop-color%3D%22%233bb6ff%22%2F%3E%3Cstop%20offset%3D%22.302%22%20stop-color%3D%22%2339afff%22%2F%3E%3Cstop%20offset%3D%22.552%22%20stop-color%3D%22%2336a3ff%22%2F%3E%3Cstop%20offset%3D%22.717%22%20stop-color%3D%22%23359fff%22%2F%3E%3Cstop%20offset%3D%22.832%22%20stop-color%3D%22%233398ff%22%2F%3E%3Cstop%20offset%3D%22.964%22%20stop-color%3D%22%233297ff%22%2F%3E%3C%2FlinearGradient%3E%3Cpath%20d%3D%22M25.27%20252.7c1.36-4.1%2058.96-201.67%20287.1-204.43%200%200%2095.66-108.2%20226%200%200%200%2035.36%2029.06%2054.76%2089.4%200%200%20171.14%2025.96%20198.84%20167.4%200%200%2057.73%20232.9-137.77%20396.53%200%200-27.53%2022.03-45.87%2032.27%200%200-40.66.06-49.06%200-17.9-.14-29.2%200-45.47%200%200%200-25-8.94-26.03-37.5%200%200-2.1-99.34-1.54-116.5%200%200%20.5-16.07-22.9-15.07%200%200-22.33-2.57-25.5%2016.63%200%200-.53%20102.47-1.03%20120.64%200%200-1.57%2030.23-35.37%2031.7%200%200-121.16%203.66-137.26-2.07%200%200-28.07-5.2-30.17-31.73%200%200-22.9-135.2-27.03-177.27%200%200-76.97-42.67-92.57-54.1%200%200%205.2%20137.77%2053.03%20196.03%200%200%208.34%207.3-8.33%2017.67%200%200-6.23%205.2-12.5%202.13%200%200-205.17-114.6-129.6-407.1%22%20fill%3D%22url%28%23a%29%22%2F%3E%3Cpath%20d%3D%22M254.93%20441.17s179%20102.03%20287.3-61.77c0%200%2087.7-114.53%2052.77-236.7%200%200%2061.5%20102.67-57.5%20261.97.03.03-100.9%20142.03-282.57%2036.5z%22%20fill%3D%22%23069%22%2F%3E%3Cpath%20d%3D%22M184.1%20417.1s12.77%2059.1-26.5%2077.7c0%200-89.33-36.8-80.3-104.77%200%200%202.8-16.96%2019.43-6.56%200%200%2039.54%2021.5%2070.74%2027.7-.04%200%2015.83%201.46%2016.63%205.93z%22%20fill%3D%22%23cee6ff%22%2F%3E%3Cpath%20d%3D%22M159.3%20317.2s13.47-57.57%2064.3-53.93c0%200%2043.2%201.16%2044.73%2060.56%200%20.04-34.03-88.83-109.03-6.63z%22%20fill%3D%22%23069%22%2F%3E%3C%2Fsvg%3E'>

</head>

<body class="bg-gray-50">

    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center">
                    <img width="48" height="48" src="data:image/svg+xml,%3Csvg%20viewBox%3D%22-58.56999999999999%20-59.93000000000002%20908.27%20797.3599999999999%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22227.0208%22%3E%3ClinearGradient%20id%3D%22a%22%20gradientUnits%3D%22userSpaceOnUse%22%20x1%3D%22400.117%22%20x2%3D%22400.117%22%20y1%3D%2222.293%22%20y2%3D%22715%22%3E%3Cstop%20offset%3D%22.018%22%20stop-color%3D%22%233bb6ff%22%2F%3E%3Cstop%20offset%3D%22.302%22%20stop-color%3D%22%2339afff%22%2F%3E%3Cstop%20offset%3D%22.552%22%20stop-color%3D%22%2336a3ff%22%2F%3E%3Cstop%20offset%3D%22.717%22%20stop-color%3D%22%23359fff%22%2F%3E%3Cstop%20offset%3D%22.832%22%20stop-color%3D%22%233398ff%22%2F%3E%3Cstop%20offset%3D%22.964%22%20stop-color%3D%22%233297ff%22%2F%3E%3C%2FlinearGradient%3E%3Cpath%20d%3D%22M25.27%20252.7c1.36-4.1%2058.96-201.67%20287.1-204.43%200%200%2095.66-108.2%20226%200%200%200%2035.36%2029.06%2054.76%2089.4%200%200%20171.14%2025.96%20198.84%20167.4%200%200%2057.73%20232.9-137.77%20396.53%200%200-27.53%2022.03-45.87%2032.27%200%200-40.66.06-49.06%200-17.9-.14-29.2%200-45.47%200%200%200-25-8.94-26.03-37.5%200%200-2.1-99.34-1.54-116.5%200%200%20.5-16.07-22.9-15.07%200%200-22.33-2.57-25.5%2016.63%200%200-.53%20102.47-1.03%20120.64%200%200-1.57%2030.23-35.37%2031.7%200%200-121.16%203.66-137.26-2.07%200%200-28.07-5.2-30.17-31.73%200%200-22.9-135.2-27.03-177.27%200%200-76.97-42.67-92.57-54.1%200%200%205.2%20137.77%2053.03%20196.03%200%200%208.34%207.3-8.33%2017.67%200%200-6.23%205.2-12.5%202.13%200%200-205.17-114.6-129.6-407.1%22%20fill%3D%22url%28%23a%29%22%2F%3E%3Cpath%20d%3D%22M254.93%20441.17s179%20102.03%20287.3-61.77c0%200%2087.7-114.53%2052.77-236.7%200%200%2061.5%20102.67-57.5%20261.97.03.03-100.9%20142.03-282.57%2036.5z%22%20fill%3D%22%23069%22%2F%3E%3Cpath%20d%3D%22M184.1%20417.1s12.77%2059.1-26.5%2077.7c0%200-89.33-36.8-80.3-104.77%200%200%202.8-16.96%2019.43-6.56%200%200%2039.54%2021.5%2070.74%2027.7-.04%200%2015.83%201.46%2016.63%205.93z%22%20fill%3D%22%23cee6ff%22%2F%3E%3Cpath%20d%3D%22M159.3%20317.2s13.47-57.57%2064.3-53.93c0%200%2043.2%201.16%2044.73%2060.56%200%20.04-34.03-88.83-109.03-6.63z%22%20fill%3D%22%23069%22%2F%3E%3C%2Fsvg%3E" alt="">
                    <h1 class="text-2xl font-bold text-gray-900">Laragon Dashboard</h1>
                    <a href="<?= htmlspecialchars($config['github_url']); ?>"
                        class="text-sm font-medium text-blue-600 hover:text-blue-500" target="_blank" rel="noopener">
                        <div class="flex justify-between items-center gap-2">
                            <svg id="fi_2111432" enable-background="new 0 0 24 24" height="24" viewBox="0 0 24 24"
                                width="24" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="m12 .5c-6.63 0-12 5.28-12 11.792 0 5.211 3.438 9.63 8.205 11.188.6.111.82-.254.82-.567 0-.28-.01-1.022-.015-2.005-3.338.711-4.042-1.582-4.042-1.582-.546-1.361-1.335-1.725-1.335-1.725-1.087-.731.084-.716.084-.716 1.205.082 1.838 1.215 1.838 1.215 1.07 1.803 2.809 1.282 3.495.981.108-.763.417-1.282.76-1.577-2.665-.295-5.466-1.309-5.466-5.827 0-1.287.465-2.339 1.235-3.164-.135-.298-.54-1.497.105-3.121 0 0 1.005-.316 3.3 1.209.96-.262 1.98-.392 3-.398 1.02.006 2.04.136 3 .398 2.28-1.525 3.285-1.209 3.285-1.209.645 1.624.24 2.823.12 3.121.765.825 1.23 1.877 1.23 3.164 0 4.53-2.805 5.527-5.475 5.817.42.354.81 1.077.81 2.182 0 1.578-.015 2.846-.015 3.229 0 .309.21.678.825.56 4.801-1.548 8.236-5.97 8.236-11.173 0-6.512-5.373-11.792-12-11.792z">
                                </path>
                            </svg>
                        </div>
                    </a>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">

            <!-- Quick Actions -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="/phpmyadmin"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200 gap-2"
                        target="_blank">
                        <span class="">
                            <img src="http://localhost/phpmyadmin/favicon.ico" alt="phpMyAdmin" width="24" height="24">
                        </span>
                        <!-- <span class="mr-3">
                            <svg fill="#F89C0E" width="24px" height="24px" viewBox="0 0 32 32" version="1.1"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M20.804 22.613c-2.973 0.042-5.808 0.573-8.448 1.514l0.183-0.057c-3.584 1.22-3.688 1.685-6.936 1.303-1.718-0.263-3.265-0.75-4.698-1.436l0.1 0.043c1.696 1.366 3.785 2.312 6.071 2.65l0.069 0.008c2.444-0.149 4.708-0.785 6.741-1.812l-0.099 0.045c2.215-0.774 4.768-1.222 7.426-1.222 0.137 0 0.273 0.001 0.409 0.004l-0.020-0c3.437 0.216 6.645 0.763 9.727 1.614l-0.332-0.078c-1.791-0.855-3.889-1.593-6.074-2.107l-0.216-0.043c-1.147-0.27-2.464-0.425-3.817-0.425-0.030 0-0.060 0-0.090 0l0.005-0zM28.568 17.517l-22.394 3.81c1.127 0.399 1.921 1.455 1.921 2.697 0 0.042-0.001 0.084-0.003 0.125l0-0.006c-0.011 0.276-0.058 0.536-0.138 0.783l0.006-0.020c2.266-1.041 4.916-1.918 7.675-2.498l0.25-0.044c1.478-0.336 3.175-0.528 4.917-0.528 1.035 0 2.055 0.068 3.054 0.2l-0.117-0.013c0.908-2.119 2.635-3.741 4.772-4.489l0.057-0.017zM10.052 5.394s3.007 1.332 4.156 6.932c0.236 0.86 0.372 1.848 0.372 2.867 0 1.569-0.321 3.063-0.902 4.42l0.028-0.073c1.648-1.56 3.878-2.518 6.332-2.518 0.854 0 1.68 0.116 2.465 0.333l-0.065-0.015c-0.462-2.86-1.676-5.378-3.431-7.418l0.017 0.020c-2.128-2.674-5.334-4.411-8.95-4.548l-0.022-0.001zM7.831 5.348c1.551 2.219 2.556 4.924 2.767 7.849l0.003 0.051c0.033 0.384 0.051 0.83 0.051 1.281 0 1.893-0.326 3.71-0.926 5.397l0.035-0.113c0.906-1.076 2.215-1.788 3.692-1.902l0.018-0.001c0.062-0.005 0.124-0.008 0.185-0.010 0.083-0.603 0.13-1.3 0.13-2.008 0-2.296-0.498-4.476-1.391-6.437l0.040 0.097c-0.865-1.999-2.516-3.519-4.552-4.19l-0.053-0.015z">
                                </path>
                            </svg>
                        </span> -->
                        <span class="font-medium text-gray-700">phpMyAdmin</span>
                    </a>

                    <a href="/mailbox.php"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200 gap-2"
                        target="_blank">
                        <span class="">
                            <svg id="fi_18561709" enable-background="new 0 0 100 100" viewBox="0 0 100 100" width="24"
                                height="24" xmlns="http://www.w3.org/2000/svg">
                                <g fill="#00adf6">
                                    <path
                                        d="m31.7299805 37.9399414h-26.0999766l12.2199717-15.3898926c1.0300293-1.3000488 2.6000366-2.0600586 4.2600098-2.0600586h55.7800293c1.6599731 0 3.2299805.7600098 4.2600098 2.0600586l12.2199707 15.3898926h-26.0999757c-.9559097-.0038223-1.8684692.4965973-2.4200439 1.2700195-.1930084.3238945-.351265.6498756-.4399719 1.020031-1.9200135 8.5200081-10.3700257 13.8699837-18.8799744 11.9499397-5.960022-1.3399658-10.6200562-5.9899902-11.9500122-11.9499512-.1060982-.3543968-.2446785-.7065315-.4500122-1.0200195-.0599976-.0699463-.1099854-.1398926-.1699829-.1999512-.0599976-.0800781-.1300049-.1500244-.2000122-.2299805-.2871361-.25177-.6026535-.4755211-.960022-.6201172h-.0100098c-.3399658-.1398925-.6900024-.2099608-1.0599975-.2199706z">
                                    </path>
                                    <path
                                        d="m97.5 43.7800293v26.9699707c0 4.8399658-3.9199829 8.7600098-8.7600098 8.7600098h-77.4799814c-4.8400269 0-8.7600098-3.9200439-8.7600098-8.7600098v-26.9699707h27.039979c2.1600342 6.3399658 7.1400146 11.3099365 13.4800415 13.4799805 11.2999878 3.8499756 23.5799561-2.1800537 27.4400024-13.4799805z">
                                    </path>
                                </g>
                            </svg>
                        </span>
                        <span class="font-medium text-gray-700">Mailbox</span>
                    </a>

                    <a href="?q=info"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200 gap-2"
                        target="_blank">
                        <span class="">
                            <svg version="1.1" id="fi_919830" xmlns="http://www.w3.org/2000/svg"
                                xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="24" height="24"
                                viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve">
                                <path style="fill:#8F9ED1;"
                                    d="M512,256c0,15.485-1.379,30.647-4.012,45.369C486.578,421.115,381.9,512,256,512c-94.856,0-177.664-51.587-221.884-128.24c-10.794-18.693-19.278-38.87-25.088-60.155C3.135,302.07,0,279.395,0,256C0,114.615,114.615,0,256,0c116.694,0,215.144,78.075,245.979,184.842C508.5,207.433,512,231.309,512,256z">
                                </path>
                                <g>
                                    <path style="fill:#F2F2F2;"
                                        d="M130.173,178.239H35.892L9.028,323.605c5.81,21.285,14.294,41.462,25.088,60.155h8.746l10.407-56.299h51.806c63.08,0,80.039-56.633,84.104-84.449C193.254,215.207,172.91,178.239,130.173,178.239z M143.851,247.703c-2.309,15.768-13.96,47.877-49.716,47.877H59.162l15.632-84.605h35.6C145.095,210.975,146.16,231.936,143.851,247.703z">
                                    </path>
                                    <path style="fill:#F2F2F2;"
                                        d="M501.979,184.842c-8.014-4.138-17.565-6.604-28.599-6.604h-94.281L341.117,383.76h44.951l10.407-56.299h51.806c28.056,0,46.989-11.201,59.705-26.091C510.621,286.647,512,271.485,512,256C512,231.309,508.5,207.433,501.979,184.842z M487.058,247.703c-2.309,15.768-13.96,47.877-49.727,47.877h-34.962l15.632-84.605h35.6C488.302,210.975,489.367,231.936,487.058,247.703z">
                                    </path>
                                    <path style="fill:#F2F2F2;"
                                        d="M309.238,178.919c-18.295,0-42.704,0-54.597,0l10.248-55.451h-44.766L182.14,328.984h44.766l21.843-118.186c8.07,0,18.79,0,28.61,0c18.991,0,31.879,4.07,29.165,21.705c-2.713,17.635-18.313,95.636-18.313,95.636h45.444 c0,0,17.635-86.818,20.348-111.237C356.717,192.484,334.334,178.919,309.238,178.919z">
                                    </path>
                                </g>
                            </svg>
                        </span>
                        <span class="font-medium text-gray-700">PHP Info</span>
                    </a>


                </div>
            </div>

            <!-- Projects -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Projects</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php if (empty($sites)): ?>
                        <div class="col-span-full">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="text-yellow-700">No projects found</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sites as $site): ?>
                            <?php $name = $site['name']; ?>
                            <?php $url = $site['url']; ?>
                            <?php $logo = $site['logo']; ?>
                            <a href="<?= htmlspecialchars($url); ?>"
                                class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow flex items-center gap-2"
                                target="_blank">
                                <?= $logo ?>
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-gray-900 group-hover:text-blue-600">
                                        <?= htmlspecialchars($name); ?>
                                    </span>
                                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Databases -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Databases</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if (empty($databases)): ?>
                        <div class="col-span-full">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="text-yellow-700">No databases found</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($databases as $db): ?>
                            <a href="/phpmyadmin/index.php?route=/database/structure&db=<?= urlencode($db['name']); ?>"
                                class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow"
                                target="_blank">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm0 3h16M4 14h16" />
                                        </svg>
                                        <span class="font-medium text-gray-900 group-hover:text-blue-600">
                                            <?= htmlspecialchars($db['name']); ?>
                                        </span>
                                    </div>
                                    <span class="text-sm text-gray-500"><?= $db['tables']; ?> tables</span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    Size: <?= number_format($db['size'], 2); ?> MB
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Information -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">System Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($systemInfo as $key => $value): ?>
                        <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                            <div class="text-sm font-medium text-gray-500"><?= htmlspecialchars($key); ?></div>
                            <div class="mt-1 text-lg font-semibold text-gray-900"><?= htmlspecialchars($value); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- PHP Extensions -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">PHP Extensions</h2>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 p-4">
                        <?php foreach ($extensions as $ext): ?>
                            <div class="text-sm text-gray-600 bg-gray-50 rounded px-3 py-1">
                                <?= htmlspecialchars($ext); ?>
                            </div>
                        <?php endforeach; ?>
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
</body>

</html>
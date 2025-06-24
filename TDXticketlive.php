<?php
header("Access-Control-Allow-Origin: https://search.library.stonybrook.edu");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Errors
error_log("Script execution started: " . date('Y-m-d H:i:s'));


// Config
$config = parse_ini_file('config.ini', true);
if (!$config) {
    error_log("Failed to load configuration file");
    echo json_encode(["success" => false, "message" => "Configuration error"]);
    exit;
}


$apiBaseUrl = $config['teamdynamix']['apiBaseUrl'];
$appId = $config['teamdynamix']['appId'];
$username = $config['teamdynamix']['username'];
$password = $config['teamdynamix']['password'];

// auth token
function getAuthToken($apiBaseUrl, $username, $password) {
    $authUrl = $apiBaseUrl . "/api/auth";
    $authData = json_encode(array(
        "username" => $username,
        "password" => $password
    ));
    
    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $authData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8'
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("Auth API Response Code: " . $httpCode);
    if ($error) {
        error_log("Auth API Error: " . $error);
    }
    
    if ($httpCode == 200) {
        error_log("Auth token successfully retrieved");
        return trim($response);
    } else {
        error_log("Auth API Failed Response: " . $response);
        return null;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = isset($_POST['badlink_report_email']) ? $_POST['badlink_report_email'] : '';
    $link = isset($_POST['current_url']) ? $_POST['current_url'] : 'No Link';
    $option_id = isset($_POST['badlink_report_option_id']) ? $_POST['badlink_report_option_id'] : '';
    $which_link = isset($_POST['badlink_report_which_link']) ? $_POST['badlink_report_which_link'] : '';
    $which_browser = isset($_POST['badlink_report_which_browser']) ? $_POST['badlink_report_which_browser'] : '';
    $on_off_campus = isset($_POST['badlink_report_on_off_campus']) ? $_POST['badlink_report_on_off_campus'] : '';
    $comments = isset($_POST['badlink_report_comments']) ? $_POST['badlink_report_comments'] : '';
    $format = isset($_POST['format']) ? $_POST['format'] : '';
    $au = isset($_POST['au']) ? $_POST['au'] : '';
    $atitle = isset($_POST['atitle']) ? $_POST['atitle'] : '';
    $stitle = isset($_POST['stitle']) ? $_POST['stitle'] : '';
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $risdate = isset($_POST['risdate']) ? $_POST['risdate'] : '';
    $volume = isset($_POST['volume']) ? $_POST['volume'] : '';
    $issue = isset($_POST['issue']) ? $_POST['issue'] : '';
    $spage = isset($_POST['spage']) ? $_POST['spage'] : '';
    $epage = isset($_POST['epage']) ? $_POST['epage'] : '';
    $pages = isset($_POST['pages']) ? $_POST['pages'] : '';
    $eissn = isset($_POST['eissn']) ? $_POST['eissn'] : '';
    $ristype = isset($_POST['ristype']) ? $_POST['ristype'] : '';
    $cop = isset($_POST['cop']) ? $_POST['cop'] : '';
    $pub = isset($_POST['pub']) ? $_POST['pub'] : '';
    $doi = isset($_POST['doi']) ? $_POST['doi'] : '';
    $pmid = isset($_POST['pmid']) ? $_POST['pmid'] : '';
    $tpages = isset($_POST['tpages']) ? $_POST['tpages'] : '';
    $orcidid = isset($_POST['orcidid']) ? $_POST['orcidid'] : '';
    $issn = isset($_POST['issn']) ? $_POST['issn'] : '';
    $addtitle = isset($_POST['addtitle']) ? $_POST['addtitle'] : '';
    $jtitle = isset($_POST['jtitle']) ? $_POST['jtitle'] : '';
    $btitle = isset($_POST['btitle']) ? $_POST['btitle'] : '';

    error_log("Received request with email: " . $email);
    error_log("Current URL: " . $link);

    $option_text = array(
        '1' => "The PDF is blank/missing pages",
        '2' => "I received a page not found or proxy-related error",
        '3' => "The link led to the resource's main page or prompted me to pay for access",
        '4' => "The link went to another website not related to the desired resource",
        '5' => "Full text was not available, only the abstract or citation",
        '6' => "Other problem with the listing, or something else went wrong"
    );
    $option_id_text = isset($option_text[$option_id]) ? $option_text[$option_id] : 'Invalid Option';

    //Isolating DocID
    $docid = '';
    if ($link !== 'No Link') {
        $url_components = parse_url($link);
        if (!empty($url_components['query'])) {
            parse_str($url_components['query'], $params);
            $docid = isset($params['docid']) ? $params['docid'] : 'No DocID found';
            
            // URL based on whether 'alma' is at the beginning of the DocID
            if (strpos($docid, 'alma') === 0) {
                $docid = 'https://search.library.stonybrook.edu/permalink/01SUNY_STB/1pi9nlb/' . $docid;
            } else {
                $docid = 'https://search.library.stonybrook.edu/permalink/01SUNY_STB/1ff40p4/' . $docid;
            }
        }
    }

    // Title handling
    $title = !empty($jtitle) ? $jtitle : $btitle;
    if (empty($title)) {
        $title = !empty($atitle) ? $atitle : "Unknown Title";
    }
    
    // Truncate title. Ticket has 100 character limit here.
    $title = substr($title, 0, 80);

    // Get auth token
    $token = getAuthToken($apiBaseUrl, $username, $password);
    
    if (!$token) {
        error_log("Failed to obtain authentication token");
        echo json_encode(["success" => false, "message" => "Authentication failed. Could not obtain token."]);
        exit;
    }
    
    error_log("Auth Token (first 10 chars): " . substr($token, 0, 10) . "...");

$description = "Broken link report for: " . $title . "\n\n";
$description .= "Issue type: " . $option_id_text . "\n";
$description .= "URL: " . $docid . "\n";
$description .= "Reported by: " . $email . "\n\n";

// Description Fields
$description .= "=== User-provided Information ===\n";
if (!empty($comments)) {
    $description .= "User Comments: " . $comments . "\n";
}
if (!empty($which_browser)) {
    $description .= "Browser: " . $which_browser . "\n";
}
if (!empty($on_off_campus)) {
    $description .= "Location: " . $on_off_campus . "\n";
}
if (!empty($which_link)) {
    $description .= "Which Link: " . $which_link . "\n";
}

$description .= "\n=== Resource Information ===\n";
if (!empty($format)) {
    $description .= "Format: " . $format . "\n";
}
if (!empty($au)) {
    $description .= "Authors: " . $au . "\n";
}
if (!empty($atitle)) {
    $description .= "Article Title: " . $atitle . "\n";
}
if (!empty($jtitle)) {
    $description .= "Journal Title: " . $jtitle . "\n";
}
if (!empty($btitle)) {
    $description .= "Book Title: " . $btitle . "\n";
}
if (!empty($addtitle)) {
    $description .= "Additional Title: " . $addtitle . "\n";
}
if (!empty($stitle)) {
    $description .= "Short Title: " . $stitle . "\n";
}

$description .= "\n=== Publication Details ===\n";
if (!empty($date)) {
    $description .= "Date: " . $date . "\n";
}
if (!empty($volume)) {
    $description .= "Volume: " . $volume . "\n";
}
if (!empty($issue)) {
    $description .= "Issue: " . $issue . "\n";
}
if (!empty($pages) || !empty($spage) || !empty($epage)) {
    $pageInfo = "";
    if (!empty($pages)) {
        $pageInfo = $pages;
    } else if (!empty($spage) && !empty($epage)) {
        $pageInfo = $spage . "-" . $epage;
    } else if (!empty($spage)) {
        $pageInfo = "Starting page: " . $spage;
    }
    $description .= "Pages: " . $pageInfo . "\n";
}
if (!empty($pub)) {
    $description .= "Publisher: " . $pub . "\n";
}

if (!empty($doi)) {
    $description .= "DOI: " . $doi . "\n";
}
if (!empty($pmid)) {
    $description .= "PMID: " . $pmid . "\n";
}
if (!empty($issn)) {
    $description .= "ISSN: " . $issn . "\n";
}
if (!empty($eissn)) {
    $description .= "eISSN: " . $eissn . "\n";
}
if (!empty($orcidid)) {
    $description .= "ORCID ID: " . $orcidid . "\n";
}

$description .= "\n=== Additional Information ===\n";
if (!empty($ristype)) {
    $description .= "RIS Type: " . $ristype . "\n";
}
if (!empty($risdate)) {
    $description .= "RIS Date: " . $risdate . "\n";
}
if (!empty($cop)) {
    $description .= "Copyright Info: " . $cop . "\n";
}
    
    // Add more than this and it's broken. Don't ask why.
    $ticketData = array(
        "Title" => "SEARCH Broken Link Report",
        "TypeID" => 2872,
        "RequestorEmail" => $email,
        "Description" => $description,
        "ServiceID" => 8426
    );
    
    // Log ticket data
    error_log("Ticket Data: " . json_encode($ticketData));

    // API endpoint
    $endpoint = $apiBaseUrl . "/api/" . $appId . "/tickets";
    $params = array(
        "EnableNotifyReviewer" => "true",
        "NotifyRequestor" => "true",
        "NotifyResponsible" => "true",
        "AllowRequestorCreation" => "true",
        "applyDefaults" => "true"
    );
    $url = $endpoint . '?' . http_build_query($params);
    
    error_log("API Endpoint URL: " . $url);

    $ch = curl_init($url);
    
    $jsonData = json_encode($ticketData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    // Set cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Bearer ' . $token
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    error_log("API Response HTTP Code: " . $httpCode);
    error_log("API Response: " . $response);
    if ($error) {
        error_log("API cURL Error: " . $error);
    }
    
    curl_close($ch);

    // Handle response
    if ($httpCode >= 200 && $httpCode < 300 && !$error) {
        $result = json_decode($response, true);
        $ticketId = isset($result['ID']) ? $result['ID'] : 'Unknown';
        error_log("Ticket #$ticketId created successfully");
        echo json_encode([
            "success" => true, 
            "message" => "Ticket #$ticketId created successfully.",
            "ticketId" => $ticketId
        ]);
    } else {
        error_log("Ticket creation failed");
        echo json_encode([
            "success" => false, 
            "message" => "Failed to create ticket. Error: " . ($error ?: $response),
            "httpCode" => $httpCode
        ]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
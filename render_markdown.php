<?php
/**
 * Server-side markdown rendering
 * This script accepts markdown content via POST and returns rendered HTML
 */

// Check if content was provided
if (!isset($_POST['content'])) {
    http_response_code(400);
    echo "Error: No markdown content provided";
    exit;
}

// Get the content
$markdown = $_POST['content'];

// Function to convert markdown to HTML
function convertMarkdownToHTML($markdown) {
    // For simplicity, we'll implement basic markdown conversions
    // Headers
    $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $markdown);
    $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^##### (.*?)$/m', '<h5>$1</h5>', $html);
    $html = preg_replace('/^###### (.*?)$/m', '<h6>$1</h6>', $html);
    
    // Bold and italic
    $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $html);
    $html = preg_replace('/__(.*?)__/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/_(.*?)_/s', '<em>$1</em>', $html);
    
    // Links
    $html = preg_replace('/\[(.*?)\]\((.*?)\)/s', '<a href="$2">$1</a>', $html);
    
    // Lists
    $html = preg_replace('/^\- (.*?)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/^\* (.*?)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/^\+ (.*?)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/^[0-9]+\. (.*?)$/m', '<li>$1</li>', $html);
    
    // Task lists
    $html = preg_replace('/- \[ \] (.*?)$/m', '<li class="task unchecked"><input type="checkbox" disabled> $1</li>', $html);
    $html = preg_replace('/- \[x\] (.*?)$/m', '<li class="task checked"><input type="checkbox" checked disabled> $1</li>', $html);
    
    // Code blocks
    $html = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $html);
    $html = preg_replace('/`(.*?)`/s', '<code>$1</code>', $html);
    
    // Blockquotes
    $html = preg_replace('/^> (.*?)$/m', '<blockquote>$1</blockquote>', $html);
    
    // Horizontal rules
    $html = preg_replace('/^---$/m', '<hr>', $html);
    
    // Paragraphs - This should be the last replacement
    $html = preg_replace('/^(?!<h|<li|<blockquote|<hr|<pre|<ul|<ol)(.*?)$/m', '<p>$1</p>', $html);
    
    // Group list items in <ul></ul> tags - basic implementation
    $html = preg_replace('/<li>(.*?)<\/li>(\s*<li>.*?<\/li>)*/s', '<ul>$0</ul>', $html);
    
    return $html;
}

// Convert the markdown to HTML
$html = convertMarkdownToHTML($markdown);

// Return the HTML
header('Content-Type: text/html');
echo $html;
?>

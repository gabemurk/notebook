<?php
/**
 * Server-side Markdown Parser for Notebook Application
 * Works with the dual database system (PostgreSQL/SQLite)
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection with dual database system
require_once __DIR__ . '/database/enhanced_db_connect.php';

// Debug log to help troubleshoot
error_log('parse_markdown.php accessed');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated',
        'html' => '<div class="error">Not logged in</div>'
    ]);
    exit;
}

/**
 * Enhanced server-side Markdown parser
 * Parses markdown syntax to HTML with support for advanced features
 */
function parseMarkdown($markdown) {
    // Log the start of parsing
    error_log('Starting to parse markdown content of length: ' . strlen($markdown));
    
    // Headers (# Heading 1, ## Heading 2, etc.)
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $markdown);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^##### (.+)$/m', '<h5>$1</h5>', $html);
    $html = preg_replace('/^###### (.+)$/m', '<h6>$1</h6>', $html);

    // Format text: bold, italic, strikethrough
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
    $html = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $html);
    
    // Extended formatting: highlight, superscript, subscript
    $html = preg_replace('/==(.+?)==/s', '<mark>$1</mark>', $html);
    $html = preg_replace('/\^(.+?)\^/s', '<sup>$1</sup>', $html);
    $html = preg_replace('/~(.+?)~/s', '<sub>$1</sub>', $html);

    // Links and images with title support
    $html = preg_replace('/!\[(.+?)\]\((.+?)(?:\s+"(.+?)")?\)/s', '<img src="$2" alt="$1" title="$3" class="md-image">', $html);
    $html = preg_replace('/\[(.+?)\]\((.+?)(?:\s+"(.+?)")?\)/s', '<a href="$2" title="$3" target="_blank" rel="noopener noreferrer">$1</a>', $html);

    // Code blocks with syntax highlighting support
    $html = preg_replace_callback('/```([a-z]*)\n([\s\S]+?)```/m', function($matches) {
        $lang = !empty($matches[1]) ? ' class="language-' . htmlspecialchars($matches[1]) . '"' : '';
        $code = htmlspecialchars($matches[2]);
        return "<pre><code$lang>$code</code></pre>";
    }, $html);
    
    // Inline code
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

    // Tables with alignment support
    $html = preg_replace_callback('/\|(.+)\|[\s]*\n\|[\s]*[-:]+[-|\s:]*\n((?:\|.+\|[\s]*\n)+)/m', function($matches) {
        // Process headers
        $headers = explode('|', trim($matches[1], '| '));
        // Process alignment row
        $alignRow = explode('|', trim(preg_replace('/[^|:-]/', '', $matches[2]), '| '));
        $rows = explode("\n", trim($matches[2]));
        
        // Build table HTML
        $table = '<table class="md-table"><thead><tr>';
        
        // Headers cells
        foreach ($headers as $i => $header) {
            $align = '';
            if (isset($alignRow[$i])) {
                if (strpos($alignRow[$i], ':') === 0 && substr($alignRow[$i], -1) === ':') {
                    $align = ' class="text-center"';
                } elseif (substr($alignRow[$i], -1) === ':') {
                    $align = ' class="text-right"';
                } else {
                    $align = ' class="text-left"';
                }
            }
            $table .= '<th' . $align . '>' . trim($header) . '</th>';
        }
        
        $table .= '</tr></thead><tbody>';
        
        // Skip the alignment row
        array_shift($rows);
        
        // Data rows
        foreach ($rows as $row) {
            $table .= '<tr>';
            $cells = explode('|', trim($row, '| '));
            
            foreach ($cells as $i => $cell) {
                $align = '';
                if (isset($alignRow[$i])) {
                    if (strpos($alignRow[$i], ':') === 0 && substr($alignRow[$i], -1) === ':') {
                        $align = ' class="text-center"';
                    } elseif (substr($alignRow[$i], -1) === ':') {
                        $align = ' class="text-right"';
                    } else {
                        $align = ' class="text-left"';
                    }
                }
                $table .= '<td' . $align . '>' . trim($cell) . '</td>';
            }
            
            $table .= '</tr>';
        }
        
        $table .= '</tbody></table>';
        return $table;
    }, $html);

    // Task Lists (- [ ] and - [x])
    $html = preg_replace_callback('/^- \[([ xX])\] (.+)$/m', function($matches) {
        $checked = (strtolower($matches[1]) === 'x') ? ' checked' : '';
        return '<div class="task-list-item"><input type="checkbox" class="task-list-item-checkbox" disabled' . $checked . '><span>' . $matches[2] . '</span></div>';
    }, $html);

    // Unordered Lists (*, -, +)
    $html = preg_replace_callback('/(?:^[*+-] (.+)$\n?)+/m', function($matches) {
        $items = explode("\n", trim($matches[0]));
        $list = '<ul class="md-list">';
        foreach ($items as $item) {
            if (preg_match('/^[*+-] (.+)$/', $item, $m)) {
                $list .= '<li>' . $m[1] . '</li>';
            }
        }
        $list .= '</ul>';
        return $list;
    }, $html);
    
    // Ordered Lists (1. 2. 3. etc)
    $html = preg_replace_callback('/(?:^\d+\. (.+)$\n?)+/m', function($matches) {
        $items = explode("\n", trim($matches[0]));
        $list = '<ol class="md-list">';
        foreach ($items as $item) {
            if (preg_match('/^\d+\. (.+)$/', $item, $m)) {
                $list .= '<li>' . $m[1] . '</li>';
            }
        }
        $list .= '</ol>';
        return $list;
    }, $html);

    // Blockquotes
    $html = preg_replace_callback('/(?:^> (.+)$\n?)+/m', function($matches) {
        $lines = explode("\n", trim($matches[0]));
        $content = '';
        foreach ($lines as $line) {
            if (preg_match('/^> (.+)$/', $line, $m)) {
                $content .= $m[1] . "\n";
            }
        }
        return '<blockquote class="md-blockquote">' . trim($content) . '</blockquote>';
    }, $html);

    // Horizontal rules
    $html = preg_replace('/^(---|\*\*\*|___)$/m', '<hr class="md-hr">', $html);

    // Paragraphs - Process last to avoid conflicts with other elements
    $html = preg_replace_callback('/(?:\n\n|\A)((?!<h[1-6]|<ul|<ol|<li|<blockquote|<pre|<hr|<table|<div).+?)(?:\n\n|\Z)/s', function($matches) {
        return "\n\n<p>" . trim($matches[1]) . "</p>\n\n";
    }, $html);

    // Clean up line breaks and ensure consistent spacing
    $html = preg_replace("/\n{2,}/", "\n\n", $html);
    
    // Add div wrapper with markdown-content class
    $html = '<div class="markdown-content">' . $html . '</div>';
    
    error_log('Finished parsing markdown, HTML length: ' . strlen($html));
    return $html;
}

/**
 * Extract metadata from markdown content
 * This helps maintain compatibility with the existing system
 */
function extractMetadata($content) {
    $metadata = [
        'title' => '',
        'category' => '',
        'tags' => []
    ];
    
    // Extract title from first heading if available
    if (preg_match('/^# (.+)$/m', $content, $matches)) {
        $metadata['title'] = trim($matches[1]);
    } else if (preg_match('/^(.{1,50})(\s|$)/m', $content, $matches)) {
        // Otherwise use first line truncated to 50 chars
        $metadata['title'] = trim($matches[1]) . (strlen($matches[1]) >= 50 ? '...' : '');
    }
    
    // Look for category metadata in format: Category: name
    if (preg_match('/^Category:\s*(.+)$/im', $content, $matches)) {
        $metadata['category'] = trim($matches[1]);
    }
    
    // Look for tags in format: Tags: tag1, tag2, tag3
    if (preg_match('/^Tags:\s*(.+)$/im', $content, $matches)) {
        $tagString = trim($matches[1]);
        $tags = explode(',', $tagString);
        $metadata['tags'] = array_map('trim', $tags);
    }
    
    return $metadata;
}

// Handle the POST request for markdown parsing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log the incoming request
    error_log('Received POST request to parse_markdown.php');
    
    // Check if markdown content is provided
    if (isset($_POST['markdown'])) {
        $markdown = $_POST['markdown'];
        $saveToDb = isset($_POST['save']) && $_POST['save'] === 'true';
        $note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : null;
        
        error_log("Markdown length: " . strlen($markdown) . ", Save to DB: $saveToDb, Note ID: $note_id");
        
        // Save to database if requested
        if ($saveToDb && $note_id > 0) {
            // Extract metadata
            $metadata = extractMetadata($markdown);
            $user_id = $_SESSION['user_id'];
            
            try {
                // Update the note in both databases using our dual database system
                $sql = "UPDATE notes SET content = ?, title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
                $result = db_execute_sync($sql, [$markdown, $metadata['title'], $note_id, $user_id]);
                
                if ($result) {
                    $saveSuccess = true;
                    $saveMessage = "Note saved successfully";
                    error_log("Successfully saved note ID $note_id for user $user_id");
                } else {
                    $saveSuccess = false;
                    $saveMessage = "Failed to save note";
                    error_log("Failed to save note ID $note_id for user $user_id");
                }
            } catch (Exception $e) {
                $saveSuccess = false;
                $saveMessage = "Error: " . $e->getMessage();
                error_log("Exception saving note: " . $e->getMessage());
            }
        }
        
        // Always parse the markdown to HTML
        $html = parseMarkdown($markdown);
        
        // Return the response as JSON
        echo json_encode([
            'success' => true,
            'html' => $html,
            'saveSuccess' => $saveToDb ? $saveSuccess : null,
            'saveMessage' => $saveToDb ? $saveMessage : null
        ]);
        exit;
    } else {
        // No markdown content provided
        error_log('No markdown content in POST request');
        echo json_encode([
            'success' => false,
            'message' => 'No markdown content provided',
            'html' => '<div class="error">No content to parse</div>'
        ]);
        exit;
    }
}

// If not a POST request
error_log('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
echo json_encode([
    'success' => false,
    'message' => 'Invalid request method',
    'html' => '<div class="error">Invalid request</div>'
]);


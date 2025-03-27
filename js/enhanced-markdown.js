/**
 * Enhanced Markdown Parser for Notebook Application
 * A pure JavaScript markdown parser with advanced features
 */

class EnhancedMarkdown {
    constructor(options = {}) {
        this.options = {
            highlight: options.highlight || false,
            linkNewWindow: options.linkNewWindow || true,
            sanitize: options.sanitize !== undefined ? options.sanitize : true,
            tables: options.tables !== undefined ? options.tables : true,
            tasklists: options.tasklists !== undefined ? options.tasklists : true,
            ...options
        };
    }

    /**
     * Parse markdown text to HTML
     */
    parse(text) {
        if (!text) return '';
        
        let html = text;
        
        // Process code blocks first (to protect from other transformations)
        const codeBlocks = [];
        html = html.replace(/```([a-z]*)\n([\s\S]+?)```/g, (match, lang, code) => {
            const id = `CODE_BLOCK_${codeBlocks.length}`;
            codeBlocks.push({ lang, code });
            return id;
        });
        
        // Basic formatting
        html = this._processHeaders(html);
        html = this._processLists(html);
        html = this._processEmphasis(html);
        html = this._processLinks(html);
        
        // Advanced features
        if (this.options.tables) {
            html = this._processTables(html);
        }
        
        if (this.options.tasklists) {
            html = this._processTasklists(html);
        }
        
        // Process code blocks (restore after other transformations)
        codeBlocks.forEach((block, index) => {
            const id = `CODE_BLOCK_${index}`;
            const highlightedCode = this._processCodeBlock(block.code, block.lang);
            html = html.replace(id, highlightedCode);
        });
        
        // Process inline code
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Process paragraphs and line breaks
        html = this._processParagraphs(html);
        
        // Horizontal rules
        html = html.replace(/^---+$/gm, '<hr>');
        
        // Process blockquotes
        html = this._processBlockquotes(html);
        
        return html;
    }
    
    /**
     * Process headers (# Heading)
     */
    _processHeaders(text) {
        return text
            .replace(/^# (.+)$/gm, '<h1>$1</h1>')
            .replace(/^## (.+)$/gm, '<h2>$1</h2>')
            .replace(/^### (.+)$/gm, '<h3>$1</h3>')
            .replace(/^#### (.+)$/gm, '<h4>$1</h4>')
            .replace(/^##### (.+)$/gm, '<h5>$1</h5>')
            .replace(/^###### (.+)$/gm, '<h6>$1</h6>');
    }
    
    /**
     * Process text emphasis (bold, italic, etc)
     */
    _processEmphasis(text) {
        return text
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/~~(.+?)~~/g, '<del>$1</del>')
            .replace(/==(.+?)==/g, '<mark>$1</mark>')
            .replace(/\^(.+?)\^/g, '<sup>$1</sup>')
            .replace(/~(.+?)~/g, '<sub>$1</sub>');
    }
    
    /**
     * Process links and images
     */
    _processLinks(text) {
        // Images first
        text = text.replace(/!\[(.+?)\]\((.+?)(?:\s+"(.+?)")?\)/g, (match, alt, src, title) => {
            const titleAttr = title ? ` title="${title}"` : '';
            return `<img src="${src}" alt="${alt}"${titleAttr}>`;
        });
        
        // Then links
        const target = this.options.linkNewWindow ? ' target="_blank" rel="noopener"' : '';
        text = text.replace(/\[(.+?)\]\((.+?)(?:\s+"(.+?)")?\)/g, (match, text, href, title) => {
            const titleAttr = title ? ` title="${title}"` : '';
            return `<a href="${href}"${titleAttr}${target}>${text}</a>`;
        });
        
        return text;
    }
    
    /**
     * Process lists (ordered and unordered)
     */
    _processLists(text) {
        // Split text into paragraphs for list processing
        const paragraphs = text.split(/\n\n+/);
        
        return paragraphs.map(para => {
            // Check if paragraph contains list items
            if (/^\s*[\*\-\+]|\d+\.\s/.test(para)) {
                // Handle unordered lists
                if (/^\s*[\*\-\+]\s/.test(para)) {
                    const listItems = para.split(/\n/).map(line => {
                        if (/^\s*[\*\-\+]\s/.test(line)) {
                            return line.replace(/^\s*[\*\-\+]\s(.+)$/, '<li>$1</li>');
                        }
                        return line;
                    }).join('');
                    
                    return `<ul>${listItems}</ul>`;
                }
                
                // Handle ordered lists
                if (/^\s*\d+\.\s/.test(para)) {
                    const listItems = para.split(/\n/).map(line => {
                        if (/^\s*\d+\.\s/.test(line)) {
                            return line.replace(/^\s*\d+\.\s(.+)$/, '<li>$1</li>');
                        }
                        return line;
                    }).join('');
                    
                    return `<ol>${listItems}</ol>`;
                }
            }
            
            return para;
        }).join('\n\n');
    }
    
    /**
     * Process paragraphs and line breaks
     */
    _processParagraphs(text) {
        // Detect paragraphs (text blocks separated by two or more newlines)
        const paragraphs = text.split(/\n{2,}/g);
        
        // Filter out paragraphs that are already wrapped in HTML tags
        const processedParagraphs = paragraphs.map(para => {
            para = para.trim();
            
            // Skip if paragraph is empty
            if (!para) return '';
            
            // Skip if paragraph is already a block element
            if (/^<(h[1-6]|ul|ol|pre|blockquote|table|div|p)/.test(para) && 
                para.endsWith(`</${para.match(/^<(h[1-6]|ul|ol|pre|blockquote|table|div|p)/)[1]}>`)) {
                return para;
            }
            
            // Wrap in paragraph tags
            return `<p>${para}</p>`;
        });
        
        // Process line breaks within paragraphs
        return processedParagraphs.join('\n\n').replace(/\n/g, '<br>\n');
    }
    
    /**
     * Process code blocks with syntax highlighting
     */
    _processCodeBlock(code, language) {
        // Simple syntax highlighting
        let highlightedCode = code.replace(/&/g, '&amp;')
                                 .replace(/</g, '&lt;')
                                 .replace(/>/g, '&gt;');
        
        // Add language class for potential CSS styling
        const langClass = language ? ` class="language-${language}"` : '';
        
        return `<pre><code${langClass}>${highlightedCode}</code></pre>`;
    }
    
    /**
     * Process tables
     */
    _processTables(text) {
        const tableRegex = /^\|(.+)\|[\s]*\n\|[\s]*[-:]+[-|\s:]*\n((?:\|.+\|[\s]*\n)+)/gm;
        
        return text.replace(tableRegex, (match, header, rows) => {
            // Process header
            const headers = header.split('|').map(cell => cell.trim()).filter(Boolean);
            const headerHtml = headers.map(cell => `<th>${cell}</th>`).join('');
            
            // Process alignment row
            const alignRow = match.split('\n')[1];
            const alignments = alignRow.split('|').map(cell => cell.trim()).filter(Boolean);
            
            // Get alignment classes (left, center, right)
            const aligns = alignments.map(cell => {
                if (cell.startsWith(':') && cell.endsWith(':')) return 'center';
                if (cell.endsWith(':')) return 'right';
                return 'left';
            });
            
            // Process data rows
            const dataRows = rows.trim().split('\n');
            const rowsHtml = dataRows.map(row => {
                const cells = row.split('|').map(cell => cell.trim()).filter(Boolean);
                
                return `<tr>${cells.map((cell, i) => {
                    const align = aligns[i] || 'left';
                    return `<td class="text-${align}">${cell}</td>`;
                }).join('')}</tr>`;
            }).join('');
            
            // Assemble table
            return `<table class="md-table">
                        <thead>
                            <tr>${headerHtml}</tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>`;
        });
    }
    
    /**
     * Process task lists [ ] and [x]
     */
    _processTasklists(text) {
        return text.replace(/^[\*\-\+]\s+\[([ x])\]\s+(.+)$/gm, (match, checked, content) => {
            const checkedAttr = checked === 'x' ? ' checked' : '';
            return `<div class="task-list-item">
                        <input type="checkbox" class="task-list-item-checkbox" disabled${checkedAttr}>
                        <span>${content}</span>
                    </div>`;
        });
    }
    
    /**
     * Process blockquotes
     */
    _processBlockquotes(text) {
        // Split by paragraphs to handle multi-paragraph blockquotes
        const paragraphs = text.split(/\n\n+/);
        
        return paragraphs.map(para => {
            if (para.trim().startsWith('> ')) {
                // Process multi-line blockquotes
                const content = para.replace(/^>\s?(.*)$/gm, '$1');
                return `<blockquote>${content}</blockquote>`;
            }
            return para;
        }).join('\n\n');
    }
}

// Export for use in the application
window.EnhancedMarkdown = EnhancedMarkdown;

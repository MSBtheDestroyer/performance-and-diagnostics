document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.copy-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            var targetSelector = button.getAttribute('data-target');
            var targetElement = document.querySelector(targetSelector);

            if (!targetElement) {
                alert('Target content not found');
                return;
            }

            // Clone the target element to avoid modifying the original content
            var clonedElement = targetElement.cloneNode(true);

            // Remove the copy button from the cloned content
            clonedElement.querySelectorAll('.copy-btn').forEach(btn => btn.remove());

            var content = getTextContent(clonedElement).trim(); // Get only text content without the button
            copyToClipboard(content);
            showCopiedMessage(button); // Show "Copied!" message
        });
    });
});

function getTextContent(element) {
    var text = '';
    element.childNodes.forEach(function(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            text += node.textContent.trim() + '\n';
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.tagName === 'H3' || node.tagName === 'H4') {
                text += '\n' + getTextContent(node).trim() + '\n';
            } else if (node.tagName === 'TABLE') {
                text += getTableText(node).trim() + '\n';
            } else {
                text += getTextContent(node).trim() + '\n';
            }
        }
    });
    return text.replace(/\n{2,}/g, '\n\n'); // Replace multiple newlines with a single newline
}

function getTableText(table) {
    var rows = table.querySelectorAll('tr');
    var tableText = '';
    rows.forEach(function(row) {
        var cells = row.querySelectorAll('th, td');
        cells.forEach(function(cell, index) {
            tableText += (index > 0 ? '\t' : '') + cell.textContent.trim();
        });
        tableText += '\n';
    });
    return tableText;
}

function copyToClipboard(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}

function showCopiedMessage(button) {
    var message = document.createElement('span');
    message.textContent = 'Copied!';
    message.style.color = 'green';
    message.style.marginLeft = '10px';
    message.style.fontSize = '12px';
    button.parentNode.insertBefore(message, button.nextSibling);

    setTimeout(function() {
        message.remove();
    }, 2000);
}

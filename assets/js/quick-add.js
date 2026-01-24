/**
 * Quick Add Component - Natural language task entry with real-time parsing
 *
 * This is the Alpine.js component for the Quick Add functionality.
 * It provides real-time parsing of natural language input, autocomplete
 * for projects (#) and tags (@), and inline highlighting of parsed elements.
 *
 * Features:
 * - Debounced parsing (150ms) via /api/v1/parse
 * - Inline highlighting with overlay technique
 * - Autocomplete dropdowns for # (projects) and @ (tags)
 * - Keyboard navigation: Enter=submit, Escape=clear, Arrow keys=navigate
 * - Click-to-remove for parsed elements
 *
 * Usage in Twig template:
 * <div x-data="quickAdd('{{ apiToken }}')" ...>
 *
 * Note: This file is provided for reference and potential future use with
 * ES modules. The component is currently embedded inline in the Twig template.
 */

export function quickAdd(apiToken) {
    return {
        apiToken: apiToken,
        inputText: '',
        parsedData: null,
        highlightedText: '',
        isSubmitting: false,
        isParsing: false,
        showAutocomplete: false,
        autocompleteItems: [],
        autocompleteIndex: 0,
        autocompleteType: null,
        autocompleteQuery: '',
        autocompleteStartPos: 0,
        errorMessage: '',
        successMessage: '',
        parseTimeout: null,
        hasErrors: false,

        init() {
            this.$watch('inputText', () => {
                this.checkForAutocomplete();
            });
        },

        async parseInput() {
            if (!this.inputText.trim()) {
                this.parsedData = null;
                this.highlightedText = '';
                return;
            }

            if (!this.apiToken) {
                this.highlightedText = this.escapeHtml(this.inputText);
                return;
            }

            this.isParsing = true;

            try {
                const response = await fetch('/api/v1/parse', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + this.apiToken
                    },
                    body: JSON.stringify({ input: this.inputText })
                });

                if (!response.ok) {
                    throw new Error('Parse request failed');
                }

                const result = await response.json();

                if (result.success && result.data) {
                    this.parsedData = result.data;
                    this.updateHighlights();
                    this.hasErrors = false;
                }
            } catch (error) {
                console.error('Parse error:', error);
                this.highlightedText = this.escapeHtml(this.inputText);
            } finally {
                this.isParsing = false;
            }
        },

        updateHighlights() {
            if (!this.parsedData?.highlights || this.parsedData.highlights.length === 0) {
                this.highlightedText = this.escapeHtml(this.inputText);
                return;
            }

            const highlights = [...this.parsedData.highlights].sort((a, b) => a.start - b.start);

            let result = '';
            let lastEnd = 0;

            for (const h of highlights) {
                if (h.start > lastEnd) {
                    result += this.escapeHtml(this.inputText.substring(lastEnd, h.start));
                }

                const highlightClass = this.getHighlightClass(h.type, h.valid);
                const text = this.escapeHtml(this.inputText.substring(h.start, h.end));
                result += `<span class="${highlightClass}">${text}</span>`;

                lastEnd = h.end;
            }

            if (lastEnd < this.inputText.length) {
                result += this.escapeHtml(this.inputText.substring(lastEnd));
            }

            this.highlightedText = result;
        },

        getHighlightClass(type, valid = true) {
            if (!valid) {
                return 'border-b-2 border-red-400 text-gray-900';
            }

            switch (type) {
                case 'date':
                    return 'bg-blue-100 text-blue-700 rounded px-0.5';
                case 'project':
                    return 'bg-indigo-50 text-indigo-600 rounded px-0.5';
                case 'tag':
                    return 'bg-gray-100 text-gray-700 rounded px-0.5';
                case 'priority':
                    return 'bg-yellow-100 text-yellow-700 rounded px-0.5';
                default:
                    return 'text-gray-900';
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            try {
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
                });
            } catch {
                return dateStr;
            }
        },

        checkForAutocomplete() {
            const input = this.$refs.input;
            const cursorPos = input.selectionStart;
            const textBeforeCursor = this.inputText.substring(0, cursorPos);

            const projectMatch = textBeforeCursor.match(/#([a-zA-Z0-9_/-]*)$/);
            if (projectMatch) {
                this.autocompleteType = 'project';
                this.autocompleteQuery = projectMatch[1];
                this.autocompleteStartPos = cursorPos - projectMatch[0].length;
                this.fetchAutocomplete();
                return;
            }

            const tagMatch = textBeforeCursor.match(/@([a-zA-Z0-9_-]*)$/);
            if (tagMatch) {
                this.autocompleteType = 'tag';
                this.autocompleteQuery = tagMatch[1];
                this.autocompleteStartPos = cursorPos - tagMatch[0].length;
                this.fetchAutocomplete();
                return;
            }

            this.showAutocomplete = false;
            this.autocompleteItems = [];
        },

        async fetchAutocomplete() {
            if (!this.apiToken) {
                this.showAutocomplete = false;
                return;
            }

            try {
                const endpoint = this.autocompleteType === 'project'
                    ? '/api/v1/autocomplete/projects'
                    : '/api/v1/autocomplete/tags';

                const response = await fetch(`${endpoint}?q=${encodeURIComponent(this.autocompleteQuery)}`, {
                    headers: {
                        'Authorization': 'Bearer ' + this.apiToken
                    }
                });

                if (!response.ok) {
                    throw new Error('Autocomplete request failed');
                }

                const result = await response.json();

                if (result.success && result.data) {
                    this.autocompleteItems = result.data.items;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.autocompleteItems.length > 0;
                }
            } catch (error) {
                console.error('Autocomplete error:', error);
                this.showAutocomplete = false;
            }
        },

        navigateAutocomplete(direction) {
            if (!this.showAutocomplete || this.autocompleteItems.length === 0) {
                return;
            }

            if (direction === 'down') {
                this.autocompleteIndex = (this.autocompleteIndex + 1) % this.autocompleteItems.length;
            } else {
                this.autocompleteIndex = (this.autocompleteIndex - 1 + this.autocompleteItems.length) % this.autocompleteItems.length;
            }
        },

        selectAutocompleteItem() {
            if (!this.showAutocomplete || this.autocompleteItems.length === 0) {
                return;
            }

            this.insertAutocompleteItem(this.autocompleteItems[this.autocompleteIndex]);
        },

        insertAutocompleteItem(item) {
            const prefix = this.autocompleteType === 'project' ? '#' : '@';
            const insertText = prefix + (item.fullPath || item.name);

            const before = this.inputText.substring(0, this.autocompleteStartPos);
            const after = this.inputText.substring(this.$refs.input.selectionStart);

            this.inputText = before + insertText + ' ' + after.trimStart();

            this.showAutocomplete = false;
            this.autocompleteItems = [];

            this.$nextTick(() => {
                this.$refs.input.focus();
                const newPos = before.length + insertText.length + 1;
                this.$refs.input.setSelectionRange(newPos, newPos);
                this.parseInput();
            });
        },

        removeToken(type, tagId = null) {
            if (!this.parsedData?.highlights) return;

            let highlightToRemove = null;

            if (type === 'tag' && tagId) {
                const tag = this.parsedData.tags?.find(t => t.id === tagId);
                if (tag) {
                    highlightToRemove = this.parsedData.highlights.find(h =>
                        h.type === 'tag' && h.text.toLowerCase().includes(tag.name.toLowerCase())
                    );
                }
            } else {
                highlightToRemove = this.parsedData.highlights.find(h => h.type === type);
            }

            if (highlightToRemove) {
                const before = this.inputText.substring(0, highlightToRemove.start);
                const after = this.inputText.substring(highlightToRemove.end);
                this.inputText = (before + after).replace(/\s+/g, ' ').trim();

                this.parseInput();
            }
        },

        async submitTask() {
            if (!this.inputText.trim()) {
                return;
            }

            this.isSubmitting = true;
            this.errorMessage = '';
            this.successMessage = '';

            try {
                if (this.apiToken) {
                    const response = await fetch('/api/v1/tasks?parse_natural_language=true', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + this.apiToken
                        },
                        body: JSON.stringify({ input_text: this.inputText })
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.error?.message || 'Failed to create task');
                    }

                    if (result.success) {
                        this.successMessage = 'Task created successfully!';
                        this.clearInput();

                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                } else {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.pathname;

                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_csrf_token';
                    csrfInput.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    form.appendChild(csrfInput);

                    const titleInput = document.createElement('input');
                    titleInput.type = 'hidden';
                    titleInput.name = 'title';
                    titleInput.value = this.inputText;
                    form.appendChild(titleInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            } catch (error) {
                console.error('Submit error:', error);
                this.errorMessage = error.message || 'Failed to create task. Please try again.';
                this.hasErrors = true;
            } finally {
                this.isSubmitting = false;
            }
        },

        clearInput() {
            this.inputText = '';
            this.parsedData = null;
            this.highlightedText = '';
            this.showAutocomplete = false;
            this.autocompleteItems = [];
            this.errorMessage = '';
            this.hasErrors = false;
            this.$refs.input.focus();
        }
    };
}

// Register the component globally if Alpine is available
if (typeof window !== 'undefined') {
    window.quickAdd = quickAdd;
}

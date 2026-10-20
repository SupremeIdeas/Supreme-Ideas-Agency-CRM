// Rich email broadcast composer (content/billing/hot-menu blueprint Phase C).
// Trix is loaded lazily — only Email Broadcast ever mounts this component — and
// wired to: (1) inline image upload via a plain fetch/XHR to a small admin
// endpoint (Trix attachments live outside Livewire's wire model), and (2) a
// "link selection to" picker over the shared DeepLinkLibrary list, alongside
// Trix's own built-in link button for freehand URLs. The Livewire `body`
// property is only pushed on `trix-change`, so the sync is one-way-per-edit,
// not a value fight between Trix's own DOM and wire:model.
export function registerEmailComposer() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('emailComposer', ({ uploadUrl = '', deepLinks = [] } = {}) => ({
            uploadUrl,
            deepLinks,
            pickedLink: '',
            linkHint: '',
            _editor: null,
            _savedRange: null,

            async init() {
                await Promise.all([import('trix'), import('trix/dist/trix.css')]);

                const trixEl = this.$el.querySelector('trix-editor');
                if (!trixEl) return;

                trixEl.addEventListener('trix-initialize', () => {
                    this._editor = trixEl.editor;
                });

                trixEl.addEventListener('trix-change', () => {
                    this.$wire.set('body', trixEl.value, false);
                });

                trixEl.addEventListener('trix-selection-change', () => {
                    if (!this._editor) return;
                    const range = this._editor.getSelectedRange();
                    if (range && range[0] !== range[1]) {
                        this._savedRange = range;
                    }
                });

                trixEl.addEventListener('trix-attachment-add', (event) => {
                    if (event.attachment.file) {
                        this.uploadAttachment(event.attachment);
                    }
                });
            },

            uploadAttachment(attachment) {
                const form = new FormData();
                form.append('image', attachment.file);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.uploadUrl, true);
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content || '');
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) attachment.setUploadProgress((e.loaded / e.total) * 100);
                });
                xhr.addEventListener('load', () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        const data = JSON.parse(xhr.responseText);
                        attachment.setAttributes({ url: data.url, href: data.url });
                    }
                });
                xhr.send(form);
            },

            // Applies a curated in-app destination to whatever text/image was
            // last actually selected — mirrors Trix's own link button, but reads
            // from the one shared DeepLinkLibrary list instead of a typed URL.
            applyDeepLink() {
                if (!this.pickedLink) return;
                if (!this._editor || !this._savedRange) {
                    this.linkHint = 'Select some text or an image first, then choose a page.';
                    this.pickedLink = '';
                    return;
                }
                this.linkHint = '';
                this._editor.setSelectedRange(this._savedRange);
                this._editor.activateAttribute('href', this.pickedLink);
                this.pickedLink = '';
            },
        }));
    });
}

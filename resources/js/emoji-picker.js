// The ONE emoji picker (Help Center / Nia overhaul, owner request): the only
// place emojis are chosen from anywhere in the platform — the customer
// composer, the staff reply box, and (indirectly, since Nia's replies are
// plain text) Nia's own messages all render whatever this picker inserts.
// Seeded from a real dataset (unicode-emoji-json, MIT) baked at build time
// into resources/js/data/emojis.json — grouped exactly like the reference
// design (recently used + 9 Unicode categories). Lazy-loaded on first open
// so the ~68KB dataset never blocks the initial page render.
export function registerEmojiPicker() {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('emojiPicker', () => ({
            open: false,
            loading: false,
            search: '',
            activeGroup: 'recent',
            groups: [],
            recents: [],

            init() {
                try {
                    this.recents = JSON.parse(localStorage.getItem('naara-recent-emojis') || '[]');
                } catch (e) {
                    this.recents = [];
                }
            },

            async toggle() {
                this.open = !this.open;
                if (this.open && this.groups.length === 0 && !this.loading) {
                    this.loading = true;
                    const mod = await import('./data/emojis.json');
                    this.groups = mod.default ?? mod;
                    this.loading = false;
                }
            },

            close() {
                this.open = false;
            },

            get filtered() {
                const q = this.search.trim().toLowerCase();
                if (q === '') {
                    return this.groups;
                }

                return this.groups
                    .map((g) => ({ ...g, emojis: g.emojis.filter((e) => e.n.toLowerCase().includes(q)) }))
                    .filter((g) => g.emojis.length > 0);
            },

            pick(emoji) {
                this.recents = [emoji, ...this.recents.filter((e) => e !== emoji)].slice(0, 24);
                try {
                    localStorage.setItem('naara-recent-emojis', JSON.stringify(this.recents));
                } catch (e) {
                    // Private browsing / storage blocked — recents just won't persist.
                }
                this.$dispatch('emoji-picked', emoji);
                this.open = false;
            },
        }));
    });
}

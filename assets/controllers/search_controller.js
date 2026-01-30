import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'results'];

    search() {
        clearTimeout(this.timeout);

        this.timeout = setTimeout(() => {
            const query = this.inputTarget.value;

            if (query.length >= 2) {
                const url = new URL(window.location.href);
                url.searchParams.set('q', query);

                fetch(url.toString(), {
                    headers: {
                        'Turbo-Frame': 'search-results',
                    },
                })
                    .then((response) => response.text())
                    .then((html) => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const frame = doc.querySelector('turbo-frame#search-results');

                        if (frame && this.hasResultsTarget) {
                            this.resultsTarget.innerHTML = frame.innerHTML;
                        }

                        window.history.replaceState({}, '', url.toString());
                    });
            }
        }, 300);
    }
}

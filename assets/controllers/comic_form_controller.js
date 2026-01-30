import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.addEventListener('submit', (e) => {
            const title = this.element.querySelector('[name$="[title]"]');
            if (title && !title.value.trim()) {
                e.preventDefault();
                title.focus();
                title.classList.add('error');
            }
        });
    }
}

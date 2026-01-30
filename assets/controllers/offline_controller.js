import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['indicator'];

    connect() {
        this.updateOnlineStatus();

        window.addEventListener('online', () => this.updateOnlineStatus());
        window.addEventListener('offline', () => this.updateOnlineStatus());
    }

    updateOnlineStatus() {
        if (this.hasIndicatorTarget) {
            if (navigator.onLine) {
                this.indicatorTarget.hidden = true;
            } else {
                this.indicatorTarget.hidden = false;
            }
        }
    }
}

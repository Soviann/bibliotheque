import { Application } from '@hotwired/stimulus';

/**
 * Démarre un contrôleur Stimulus dans un conteneur DOM isolé.
 *
 * @param {typeof import('@hotwired/stimulus').Controller} ControllerClass
 * @param {string} identifier - Identifiant Stimulus (ex: 'tomes-collection')
 * @param {string} html - HTML du conteneur (doit contenir data-controller)
 * @returns {Promise<{application: Application, element: HTMLElement}>}
 */
export async function startStimulusController(ControllerClass, identifier, html) {
    const container = document.createElement('div');
    container.innerHTML = html;
    document.body.appendChild(container);

    const element = container.querySelector(`[data-controller="${identifier}"]`);

    const application = Application.start(container);
    application.register(identifier, ControllerClass);

    // Attendre que Stimulus connecte le contrôleur
    await new Promise((resolve) => setTimeout(resolve, 0));

    return { application, element };
}

/**
 * Arrête l'application Stimulus et nettoie le DOM.
 *
 * @param {Application} application
 */
export function stopStimulusController(application) {
    application.stop();

    // Supprime tous les conteneurs ajoutés au body
    while (document.body.firstChild) {
        document.body.firstChild.remove();
    }
}

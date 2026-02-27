/**
 * Configuration globale des mocks pour les tests Vitest.
 * Fournit des implémentations en mémoire de fetch, localStorage, Cache API et crypto.
 */

// --- fetch mock ---
global.fetch = vi.fn();

// --- localStorage mock (implémentation en mémoire) ---
const localStorageMock = (() => {
    let store = {};
    return {
        clear: () => { store = {}; },
        getItem: (key) => store[key] ?? null,
        key: (index) => Object.keys(store)[index] ?? null,
        get length() { return Object.keys(store).length; },
        removeItem: (key) => { delete store[key]; },
        setItem: (key, value) => { store[key] = String(value); },
    };
})();

Object.defineProperty(global, 'localStorage', { value: localStorageMock });

// --- Cache API mock ---
function createCacheMock() {
    const cacheStore = new Map();
    return {
        delete: vi.fn(async (request) => cacheStore.delete(String(request))),
        match: vi.fn(async (request) => cacheStore.get(String(request)) || undefined),
        put: vi.fn(async (request, response) => { cacheStore.set(String(request), response); }),
    };
}

let cachesMap = new Map();

const cachesMock = {
    delete: vi.fn(async (name) => cachesMap.delete(name)),
    open: vi.fn(async (name) => {
        if (!cachesMap.has(name)) {
            cachesMap.set(name, createCacheMock());
        }
        return cachesMap.get(name);
    }),
};

Object.defineProperty(global, 'caches', { configurable: true, value: cachesMock, writable: true });

// --- crypto.getRandomValues mock ---
if (!global.crypto) {
    global.crypto = {};
}
if (!global.crypto.getRandomValues) {
    global.crypto.getRandomValues = (array) => {
        for (let i = 0; i < array.length; i++) {
            array[i] = Math.floor(Math.random() * 256);
        }
        return array;
    };
}

// --- Reset après chaque test ---
afterEach(() => {
    vi.restoreAllMocks();
    localStorage.clear();
    global.fetch = vi.fn();
    // Reset le cache API (nouvelles instances pour chaque test)
    cachesMap = new Map();
    cachesMock.open.mockClear();
    cachesMock.delete.mockClear();
});

interface SyncManager {
  getTags(): Promise<string[]>;
  register(tag: string): Promise<void>;
}

interface SyncEvent extends ExtendableEvent {
  readonly lastChance: boolean;
  readonly tag: string;
}

interface ServiceWorkerRegistration {
  readonly sync: SyncManager;
}

interface ServiceWorkerGlobalScopeEventMap {
  sync: SyncEvent;
}

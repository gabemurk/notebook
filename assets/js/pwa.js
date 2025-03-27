// IndexedDB setup
const dbName = 'notebookDB';
const dbVersion = 1;

let db;
let isOnline = navigator.onLine;
let syncInProgress = false;

// Initialize IndexedDB
const initDB = () => {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(dbName, dbVersion);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            db = request.result;
            resolve(db);
        };
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            // Create object stores
            if (!db.objectStoreNames.contains('notes')) {
                const notesStore = db.createObjectStore('notes', { keyPath: 'id', autoIncrement: true });
                notesStore.createIndex('syncStatus', 'syncStatus');
            }
            
            if (!db.objectStoreNames.contains('syncQueue')) {
                db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
};

// Database status monitoring
const checkDatabaseStatus = async () => {
    try {
        const response = await fetch('/api/sync_status.php');
        const data = await response.json();
        
        updateDatabaseStatus(data.status);
    } catch (error) {
        console.error('Error checking database status:', error);
        updateDatabaseStatus({
            postgresql: { connected: false },
            sqlite: { connected: true },
            last_sync: null
        });
    }
};

const updateDatabaseStatus = (status) => {
    const pgStatus = document.querySelector('.db-status .postgresql + .status-indicator .status-dot');
    const sqliteStatus = document.querySelector('.db-status .sqlite + .status-indicator .status-dot');
    const lastSyncTime = document.getElementById('lastSyncTime');
    
    // Update PostgreSQL status
    pgStatus.className = `status-dot ${status.postgresql.connected ? 'connected' : 'disconnected'}`;
    
    // Update SQLite status
    sqliteStatus.className = `status-dot ${status.sqlite.connected ? 'connected' : 'disconnected'}`;
    
    // Update last sync time
    if (status.last_sync) {
        const syncTime = new Date(status.last_sync);
        lastSyncTime.textContent = timeAgo(syncTime);
    }
};

// Online/Offline handling
window.addEventListener('online', () => {
    isOnline = true;
    showToast('You are back online', 'success');
    syncNotes();
});

window.addEventListener('offline', () => {
    isOnline = false;
    showToast('You are offline. Changes will be synced when you\'re back online', 'warning');
});

// Note synchronization
const syncNotes = async () => {
    if (!isOnline || syncInProgress) return;
    
    syncInProgress = true;
    updateSyncStatus('syncing');
    
    try {
        const tx = db.transaction('syncQueue', 'readonly');
        const store = tx.objectStore('syncQueue');
        const changes = await store.getAll();
        
        if (changes.length === 0) {
            syncInProgress = false;
            updateSyncStatus('connected');
            return;
        }
        
        for (const change of changes) {
            try {
                const response = await fetch('/api/sync.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(change)
                });
                
                if (!response.ok) throw new Error('Sync failed');
                
                // Remove from sync queue
                const deleteTx = db.transaction('syncQueue', 'readwrite');
                const deleteStore = deleteTx.objectStore('syncQueue');
                await deleteStore.delete(change.id);
                
            } catch (error) {
                console.error('Error syncing change:', error);
                showToast('Error syncing changes', 'error');
            }
        }
        
        showToast('Changes synced successfully', 'success');
        checkDatabaseStatus();
        
    } catch (error) {
        console.error('Error during sync:', error);
        showToast('Error syncing changes', 'error');
    }
    
    syncInProgress = false;
    updateSyncStatus('connected');
};

// UI Updates
const updateSyncStatus = (status) => {
    const offlineBanner = document.querySelector('.status-banner.offline');
    const syncingBanner = document.querySelector('.status-banner.syncing');
    
    offlineBanner.classList.toggle('visible', !isOnline);
    syncingBanner.classList.toggle('visible', status === 'syncing');
};

const showToast = (message, type = 'info') => {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    
    const container = document.getElementById('toastContainer');
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
};

const timeAgo = (date) => {
    const seconds = Math.floor((new Date() - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval > 1) return interval + ' years ago';
    
    interval = Math.floor(seconds / 2592000);
    if (interval > 1) return interval + ' months ago';
    
    interval = Math.floor(seconds / 86400);
    if (interval > 1) return interval + ' days ago';
    
    interval = Math.floor(seconds / 3600);
    if (interval > 1) return interval + ' hours ago';
    
    interval = Math.floor(seconds / 60);
    if (interval > 1) return interval + ' minutes ago';
    
    return 'just now';
};

// Mobile menu toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');

menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
});

// Initialize app
const init = async () => {
    try {
        await initDB();
        await checkDatabaseStatus();
        
        // Start periodic checks
        setInterval(checkDatabaseStatus, 30000); // Every 30 seconds
        setInterval(syncNotes, 60000); // Every minute
        
        // Initial sync
        if (isOnline) syncNotes();
        
    } catch (error) {
        console.error('Error initializing app:', error);
        showToast('Error initializing app', 'error');
    }
};

// Start the app
init();

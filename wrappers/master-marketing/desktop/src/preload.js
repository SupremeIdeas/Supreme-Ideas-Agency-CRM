'use strict';
// The only thing the page can learn about the shell: that it is the desktop app, its version and the OS. No Node, no files, no secrets.
const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('NaaraDesktop', { info: () => ipcRenderer.invoke('naara:info') });
// While a protected video plays the site asks for the window to be hidden from screen capture and recording; it turns it back off when the video stops. A boolean, nothing else.
contextBridge.exposeInMainWorld('naaraDesktop', { setContentProtection: (on) => ipcRenderer.send('naara:content-protection', !!on) });

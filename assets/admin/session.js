// Berechtigungszustand der aktuellen Redaktions-Sitzung (Rollen/Modulzugriff/Seitenzugriff, siehe
// `AuthenticationController`) sowie die daraus abgeleiteten Berechtigungsprüfungen — von jedem
// `admin/*.js`-Bereich genutzt, um Aktionen/Buttons ein-/auszublenden (serverseitig ohnehin erneut
// geprüft, siehe `benutzer-konzept.md`).

let currentRoles = [];
let currentModuleAccess = {};
let currentPageAccess = null;

/**
 * Übernimmt die Sitzungsdaten nach Login/Refresh (`user` aus der Antwort von
 * `AuthenticationController`) — mit `null` (siehe `forceLogout`) wird der Zustand zurückgesetzt.
 */
const setSessionState = (user) => {
    currentRoles = user?.roles ?? [];
    currentModuleAccess = user?.moduleAccess ?? {};
    currentPageAccess = user?.pageAccess ?? null;
};

const hasAnyRole = (...roles) => currentRoles.some((role) => roles.includes(role));
const isGlobalAdministrator = () => hasAnyRole('admin', 'super_admin');
const hasModule = (module) => typeof currentModuleAccess[module] === 'string';
const moduleRole = (module) => currentModuleAccess[module] || null;
const canEditModule = (module) => hasModule(module)
    && (isGlobalAdministrator() || moduleRole(module) === 'editor');
const canViewPages = () => hasModule('pages');
const isPageAccessRestricted = () => currentPageAccess !== null && !isGlobalAdministrator();
const pageRole = (pageId) => pageId && currentPageAccess ? currentPageAccess[pageId] || null : null;
const canEditPages = (pageId = null) => canViewPages() && (
    isGlobalAdministrator()
    || (isPageAccessRestricted()
        ? ['editor', 'publisher'].includes(pageRole(pageId))
        : ['editor', 'publisher', 'moderator'].includes(moduleRole('pages')))
);
const canPublishPages = (pageId = null) => canViewPages() && (
    isGlobalAdministrator()
    || (isPageAccessRestricted() ? pageRole(pageId) === 'publisher' : moduleRole('pages') === 'publisher')
);
const canManagePageStructure = () => canEditPages() && !isPageAccessRestricted();

export {
    setSessionState,
    hasAnyRole,
    isGlobalAdministrator,
    hasModule,
    moduleRole,
    canEditModule,
    canViewPages,
    isPageAccessRestricted,
    pageRole,
    canEditPages,
    canPublishPages,
    canManagePageStructure,
};

const STORAGE_ID = 'owner_acting_director_id';
const STORAGE_NAME = 'owner_acting_director_name';
const STORAGE_PHONE = 'owner_acting_director_phone';
const HEADER = 'X-Acting-Director-Id';

function axiosHeaders() {
  return window.axios?.defaults?.headers?.common ?? null;
}

function setActingHeader(id) {
  const headers = axiosHeaders();
  if (!headers) return;
  if (id) {
    headers[HEADER] = String(id);
  } else {
    delete headers[HEADER];
  }
}

/**
 * Superadmin kompaniya kabinetiga kirish.
 * `id` — companies.id (API X-Acting-Director-Id / company scope).
 */
export function setActingCompany(company) {
  if (!company?.id) {
    clearActingDirector();
    return;
  }
  sessionStorage.setItem(STORAGE_ID, String(company.id));
  sessionStorage.setItem(STORAGE_NAME, company.name || company.admin?.name || '');
  sessionStorage.setItem(STORAGE_PHONE, company.phone || company.admin?.phone || '');
  setActingHeader(company.id);
}

/** @deprecated use setActingCompany — id kompaniya id bo‘lishi kerak */
export function setActingDirector(director) {
  if (!director?.id) {
    clearActingDirector();
    return;
  }
  // Eski director user id o‘rniga company_id bo‘lsa — uni afzal ko‘ramiz
  const companyId = director.company_id || director.companyId || director.id;
  setActingCompany({
    id: companyId,
    name: director.name || director.company_name || '',
    phone: director.phone || '',
  });
}

/** @deprecated use setActingCompany */
export function setActingDirectorId(id) {
  if (id) {
    sessionStorage.setItem(STORAGE_ID, String(id));
    setActingHeader(id);
  } else {
    clearActingDirector();
  }
}

export function getActingDirectorId() {
  return sessionStorage.getItem(STORAGE_ID);
}

export function getActingDirector() {
  const id = getActingDirectorId();
  if (!id) return null;
  return {
    id,
    name: sessionStorage.getItem(STORAGE_NAME) || '',
    phone: sessionStorage.getItem(STORAGE_PHONE) || '',
  };
}

export function clearActingDirector() {
  sessionStorage.removeItem(STORAGE_ID);
  sessionStorage.removeItem(STORAGE_NAME);
  sessionStorage.removeItem(STORAGE_PHONE);
  setActingHeader(null);
}

export function restoreActingDirectorHeader() {
  const id = getActingDirectorId();
  if (id) {
    setActingHeader(id);
  }
}

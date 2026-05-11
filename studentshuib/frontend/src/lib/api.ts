import axios from 'axios';

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || '/api/v1',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  withCredentials: false,
});

// Attach bearer token on every request
api.interceptors.request.use((config) => {
  const token = typeof window !== 'undefined' ? localStorage.getItem('sh_token') : null;
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Handle 401 globally — redirect to login
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401 && typeof window !== 'undefined') {
      localStorage.removeItem('sh_token');
      localStorage.removeItem('sh_user');
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export default api;

// ---- Auth ----
export const authApi = {
  login:          (email: string, password: string)                           => api.post('/auth/login', { email, password }),
  register:       (data: object)                              => api.post('/auth/register', data),
  logout:         ()                                                          => api.post('/auth/logout'),
  me:             ()                                                          => api.get('/auth/me'),
  updateProfile:  (data: object)                              => api.put('/auth/profile', data),
  changePassword: (data: object)                              => api.put('/auth/password', data),
  forgotPassword: (email: string)                                             => api.post('/auth/forgot-password', { email }),
  resetPassword:  (email: string, token: string, password: string, password_confirmation: string) =>
    api.post('/auth/reset-password', { email, token, password, password_confirmation }),
};

// ---- Student ----
export const studentApi = {
  formTypes:   (params?: Record<string, unknown>)       => api.get('/student/form-types', { params }),
  formType:    (slug: string)                           => api.get(`/student/form-types/${slug}`),
  submissions: (params?: Record<string, unknown>)       => api.get('/student/submissions', { params }),
  submission:  (ref: string)                            => api.get(`/student/submissions/${ref}`),
  submit:      (data: object)          => api.post('/student/submissions', data),
  resubmit:    (ref: string, data: object) => api.put(`/student/submissions/${ref}`, data),
  cancel:      (ref: string)                            => api.delete(`/student/submissions/${ref}`),
  addComment:  (ref: string, body: string)              => api.post(`/student/submissions/${ref}/comments`, { body }),
  uploadDocument: (ref: string, formData: FormData)     => api.post(`/student/submissions/${ref}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  notifications: (params?: Record<string, unknown>)     => api.get('/student/notifications', { params }),
  markRead:    (id: number)                             => api.put(`/student/notifications/${id}/read`),
  markAllRead: ()                                       => api.put('/student/notifications/read-all'),
};

// ---- Admin ----
export const adminApi = {
  dashboard:      (params?: Record<string, unknown>)         => api.get('/admin/dashboard', { params }),
  submissions:    (params?: Record<string, unknown>)         => api.get('/admin/submissions', { params }),
  submission:     (ref: string)                              => api.get(`/admin/submissions/${ref}`),
  getSubmission:  (ref: string)                              => api.get(`/admin/submissions/${ref}`),
  updateStatus:   (ref: string, status: string, comment?: string) =>
    api.put(`/admin/submissions/${ref}/status`, { status, comment }),
  assign:         (ref: string, userId: number)              => api.put(`/admin/submissions/${ref}/assign`, { user_id: userId }),
  addComment:     (ref: string, body: string, type: 'internal' | 'external') =>
    api.post(`/admin/submissions/${ref}/comments`, { body, is_internal: type === 'internal' }),
  uploadDoc:      (ref: string, formData: FormData)          => api.post(`/admin/submissions/${ref}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  uploadDocument: (ref: string, formData: FormData)          => api.post(`/admin/submissions/${ref}/documents`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  bulkStatus:     (refNos: string[], status: string, comment?: string) =>
    api.post('/admin/submissions/bulk-status', { ref_nos: refNos, status, comment }),
  exportCsv:      (params?: Record<string, unknown>)         => api.get('/admin/submissions/export', { params, responseType: 'blob' }),
  exportPdf:      (params?: Record<string, unknown>)         => api.get('/admin/reports/analytics',  { params, responseType: 'blob' }),
  staff:          ()                                         => api.get('/admin/staff'),
  departments:    ()                                         => api.get('/admin/departments'),
  notifications:  ()                                         => api.get('/admin/notifications'),
  markRead:       (id: number)                               => api.put(`/admin/notifications/${id}/read`),
  markAllRead:    ()                                         => api.put('/admin/notifications/read-all'),
};

// ---- Super Admin ----
export const superApi = {
  users:            (params?: Record<string, unknown>)         => api.get('/super/users', { params }),
  createUser:       (data: object)            => api.post('/super/users', data),
  updateUser:       (id: number, data: object)=> api.put(`/super/users/${id}`, data),
  toggleUser:       (id: number)                               => api.put(`/super/users/${id}/toggle-active`),
  deleteUser:       (id: number)                               => api.delete(`/super/users/${id}`),
  formTypes:        (params?: Record<string, unknown>)         => api.get('/super/form-types', { params }),
  createFormType:   (data: object)            => api.post('/super/form-types', data),
  updateFormType:   (id: number, data: object)=> api.put(`/super/form-types/${id}`, data),
  toggleFormType:   (id: number)                               => api.put(`/super/form-types/${id}/toggle-active`),
  createFT:         (data: object)            => api.post('/super/form-types', data),   // legacy alias
  updateFT:         (id: number, data: object)=> api.put(`/super/form-types/${id}`, data),
  toggleFT:         (id: number)                               => api.put(`/super/form-types/${id}/toggle-active`),
  departments:      (params?: Record<string, unknown>)         => api.get('/super/departments', { params }),
  createDepartment: (data: object)            => api.post('/super/departments', data),
  updateDepartment: (id: number, data: object)=> api.put(`/super/departments/${id}`, data),
  deleteDepartment: (id: number)                               => api.delete(`/super/departments/${id}`),
  createDept:       (data: object)            => api.post('/super/departments', data),  // legacy alias
  updateDept:       (id: number, data: object)=> api.put(`/super/departments/${id}`, data),
  formType:         (id: number)                               => api.get(`/super/form-types/${id}`),
  formTypeFields:   (id: number)                               => api.get(`/super/form-types/${id}/fields`),
  createField:      (id: number, data: object) => api.post(`/super/form-types/${id}/fields`, data),
  updateField:      (id: number, fid: number, data: object) => api.put(`/super/form-types/${id}/fields/${fid}`, data),
  deleteField:      (id: number, fid: number)                  => api.delete(`/super/form-types/${id}/fields/${fid}`),
  reorderFields:    (id: number, order: number[])              => api.post(`/super/form-types/${id}/fields/reorder`, { order }),
  analytics:        (days?: number)                            => api.get('/super/analytics/overview', { params: days ? { days } : undefined }),
  slaReport:        ()                                         => api.get('/super/analytics/sla'),
  deptReport:       (days?: number)                            => api.get('/super/analytics/departments', { params: days ? { days } : undefined }),
  auditLogs:        (params?: Record<string, unknown>)         => api.get('/super/audit-logs', { params }),
  // System settings (semester window etc.)
  systemSettings:       ()                                     => api.get('/super/settings'),
  updateSystemSettings: (data: object)        => api.put('/super/settings', data),
};

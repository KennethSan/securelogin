import axios from 'axios';

// Configure axios instance with proper settings for Laravel Sanctum
const api = axios.create({
  baseURL: 'https://localhost', // Changed to HTTPS
  withCredentials: true, // Essential for session-based auth
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// CSRF token management
let csrfToken: string | null = null;

// Get CSRF cookie before making any unsafe requests
export const getCsrfCookie = async (): Promise<void> => {
  try {
    await api.get('/sanctum/csrf-cookie');
    // The CSRF token is automatically set in cookies by Laravel
    // We'll let the browser handle the XSRF-TOKEN header automatically
  } catch (error) {
    console.error('Failed to get CSRF cookie:', error);
    throw error;
  }
};

// Authentication service
export const authService = {
  // Login user
  async login(email: string, password: string) {
    await getCsrfCookie(); // Get CSRF token first
    
    const response = await api.post('/login', {
      email,
      password,
    });
    
    return response.data;
  },

  // Register user
  async register(name: string, email: string, password: string, password_confirmation: string) {
    await getCsrfCookie();
    
    const response = await api.post('/register', {
      name,
      email,
      password,
      password_confirmation,
    });
    
    return response.data;
  },

  // Get current user
  async getCurrentUser() {
    const response = await api.get('/api/me');
    return response.data;
  },

  // Logout user
  async logout() {
    const response = await api.post('/logout');
    return response.data;
  },

  // Request password reset
  async forgotPassword(email: string) {
    await getCsrfCookie();
    
    const response = await api.post('/forgot-password', {
      email,
    });
    
    return response.data;
  },

  // Reset password
  async resetPassword(email: string, password: string, password_confirmation: string, token: string) {
    await getCsrfCookie();
    
    const response = await api.post('/reset-password', {
      email,
      password,
      password_confirmation,
      token,
    });
    
    return response.data;
  },
};

// Response interceptor to handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Redirect to login on 401
      window.location.href = '/login';
    }
    
    if (error.response?.status === 419) {
      // CSRF token mismatch - get new token and retry
      console.warn('CSRF token mismatch, refreshing...');
      return getCsrfCookie().then(() => {
        // Retry the original request
        return api.request(error.config);
      });
    }
    
    return Promise.reject(error);
  }
);

export default api;
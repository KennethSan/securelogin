import axios from 'axios';

// Configure axios instance with proper settings for Laravel Sanctum
const api = axios.create({
  baseURL: 'https://localhost:8443',
  withCredentials: true, // Essential for session-based auth
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest', // Important for Laravel
  },
  timeout: 10000, // 10 second timeout
});

// Get CSRF cookie before making any unsafe requests
export const getCsrfCookie = async (): Promise<void> => {
  try {
    console.log('Getting CSRF cookie...');
    await api.get('/sanctum/csrf-cookie');
    console.log('CSRF cookie obtained successfully');
  } catch (error) {
    console.error('Failed to get CSRF cookie:', error);
    throw error;
  }
};

// Authentication service
export const authService = {
  // Login user
  async login(email: string, password: string) {
    try {
      console.log('Starting login process for:', email);
      await getCsrfCookie(); // Get CSRF token first

      console.log('Sending login request...');
      const response = await api.post('/login', {
        email,
        password,
      });

      console.log('Login response received:', response.data);
      return response.data;
    } catch (error: any) {
      console.error('Login error details:', {
        status: error.response?.status,
        statusText: error.response?.statusText,
        data: error.response?.data,
        message: error.message
      });
      throw error;
    }
  },

  // Register user
  async register(name: string, email: string, password: string, password_confirmation: string) {
    try {
      console.log('Starting registration for:', email);
      await getCsrfCookie();

      const response = await api.post('/register', {
        name,
        email,
        password,
        password_confirmation,
      });

      console.log('Registration successful:', response.data);
      return response.data;
    } catch (error: any) {
      console.error('Registration failed:', error.response?.data);
      throw error;
    }
  },

  // Get current user - only call when we expect to be authenticated
  async getCurrentUser() {
    try {
      // Check if we have any indication we might be authenticated
      const token = localStorage.getItem('auth_token');
      if (!token) {
        throw new Error('No authentication token found');
      }
      
      const response = await api.get('/api/me');
      return response.data;
    } catch (error: any) {
      console.error('Failed to get current user:', error.response?.data);
      // Clear any stale tokens
      localStorage.removeItem('auth_token');
      throw error;
    }
  },

  // Logout user
  async logout() {
    try {
      const response = await api.post('/logout');
      localStorage.removeItem('auth_token');
      return response.data;
    } catch (error: any) {
      console.error('Logout failed:', error.response?.data);
      // Even if logout fails on server, clear local storage
      localStorage.removeItem('auth_token');
      throw error;
    }
  },

  // Request password reset
  async forgotPassword(email: string) {
    try {
      await getCsrfCookie();
      const response = await api.post('/forgot-password', { email });
      return response.data;
    } catch (error: any) {
      console.error('Forgot password failed:', error.response?.data);
      throw error;
    }
  },

  // Reset password
  async resetPassword(email: string, password: string, password_confirmation: string, token: string) {
    try {
      await getCsrfCookie();
      const response = await api.post('/reset-password', {
        email,
        password,
        password_confirmation,
        token,
      });
      return response.data;
    } catch (error: any) {
      console.error('Password reset failed:', error.response?.data);
      throw error;
    }
  },

  // Send email verification notification
  async sendVerificationEmail() {
    try {
      const response = await api.post('/api/email/verification-notification');
      return response.data;
    } catch (error: any) {
      console.error('Send verification email failed:', error.response?.data);
      throw error;
    }
  },

  // Verify email
  async verifyEmail(id: string, hash: string, signature: string) {
    try {
      const response = await api.get(`/api/email/verify/${id}/${hash}?signature=${signature}`);
      return response.data;
    } catch (error: any) {
      console.error('Email verification failed:', error.response?.data);
      throw error;
    }
  },
};

// Response interceptor to handle errors
api.interceptors.response.use(
  (response) => {
    console.log('API Response:', {
      url: response.config.url,
      status: response.status,
      data: response.data
    });
    return response;
  },
  (error) => {
    console.error('API Error:', {
      url: error.config?.url,
      status: error.response?.status,
      data: error.response?.data,
      message: error.message
    });

    // Only redirect to login if not already on login page and it's an auth error
    if (error.response?.status === 401 &&
        !window.location.pathname.includes('/login') &&
        !window.location.pathname.includes('/register') &&
        !window.location.pathname.includes('/email/verify') &&
        !window.location.pathname.includes('/dev/verify')) {
      console.log('Redirecting to login due to 401 error');
      window.location.href = '/login';
    }

    if (error.response?.status === 419) {
      // CSRF token mismatch - get new token and retry
      console.warn('CSRF token mismatch, refreshing...');
      return getCsrfCookie().then(() => {
        return api.request(error.config);
      });
    }

    return Promise.reject(error);
  }
);

export default api;
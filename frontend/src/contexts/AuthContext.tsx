import { createContext, useState, useEffect, useContext, ReactNode } from 'react';
import axios from 'axios';

interface User {
  id: number;
  name: string;
  email: string;
  created_at: string;
  updated_at: string;
  email_verified_at: string | null;
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  error: string | null;
  login: (email: string, password: string) => Promise<boolean>;
  logout: () => Promise<void>;
  register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<boolean>;
}

const AuthContext = createContext<AuthContextType | null>(null);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Check if user is already logged in (on app load)
  useEffect(() => {
    const token = localStorage.getItem('token');
    if (token) {
      fetchUser();
    } else {
      setLoading(false);
    }
  }, []);

  // Fetch authenticated user
  const fetchUser = async () => {
    try {
      const token = localStorage.getItem('token');
      if (!token) {
        setLoading(false);
        return;
      }

      const response = await axios.get('http://localhost:8000/api/user', {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      });
      
      setUser(response.data);
    } catch (err) {
      console.error("Error fetching user:", err);
      localStorage.removeItem('token');
      setError('Session expired. Please login again.');
    } finally {
      setLoading(false);
    }
  };

  // Login function
  const login = async (email: string, password: string) => {
    try {
      setError(null);
      const response = await axios.post('http://localhost:8000/api/login', {
        email,
        password
      });
      
      localStorage.setItem('token', response.data.token);
      setUser(response.data.user);
      return true;
    } catch (err: any) {
      setError(err.response?.data?.message || 'Login failed. Please check your credentials.');
      return false;
    }
  };

  // Register function
  const register = async (name: string, email: string, password: string, passwordConfirmation: string) => {
    try {
      setError(null);
      const response = await axios.post('http://localhost:8000/api/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation
      });
      
      localStorage.setItem('token', response.data.token);
      setUser(response.data.user);
      return true;
    } catch (err: any) {
      setError(err.response?.data?.message || 'Registration failed.');
      return false;
    }
  };

  // Logout function
  const logout = async () => {
    try {
      const token = localStorage.getItem('token');
      await axios.post('http://localhost:8000/api/logout', {}, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      });
    } catch (err) {
      console.error("Logout error:", err);
    } finally {
      localStorage.removeItem('token');
      setUser(null);
    }
  };

  return (
    <AuthContext.Provider value={{ 
      user, 
      loading, 
      error, 
      login, 
      logout, 
      register 
    }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
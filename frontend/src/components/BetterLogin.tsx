import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function BetterLogin() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [showVerificationPrompt, setShowVerificationPrompt] = useState(false);
  const navigate = useNavigate();
  const { login } = useAuth();

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setError('');
    setShowVerificationPrompt(false);
    
    try {
      await login(email, password);
      console.log('Login successful, redirecting to dashboard...');
      navigate('/dashboard', { replace: true });
      
    } catch (error: any) {
      console.error('Login failed:', error);
      
      // Handle email verification requirement specifically
      if (error.response?.status === 403 && error.response?.data?.email_verification_required) {
        setError('Your email address needs to be verified before you can login.');
        setShowVerificationPrompt(true);
      } 
      // Handle unverified email with different message
      else if (error.response?.data?.message?.includes('verify') || 
               error.response?.data?.message?.includes('verification')) {
        setError('Please verify your email address before logging in.');
        setShowVerificationPrompt(true);
      }
      // Handle other authentication errors
      else if (error.response?.status === 401) {
        setError('Invalid email or password. Please check your credentials and try again.');
      }
      // Handle network or server errors
      else if (error.response?.status >= 500) {
        setError('Server error. Please try again later.');
      }
      // Handle other errors
      else {
        setError(error.response?.data?.message || 'Login failed. Please try again.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleResendVerification = async () => {
    try {
      setIsLoading(true);
      // Store email for verification page
      localStorage.setItem('pendingVerificationEmail', email);
      navigate('/email/verify');
    } catch (error) {
      console.error('Failed to navigate to verification page:', error);
      setError('Failed to redirect to verification page. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleViewVerificationEmail = () => {
    alert(`Since this is a development environment, verification emails are logged to the backend console instead of being sent to your email.\n\nTo see your verification email:\n1. Open a terminal\n2. Run: docker logs secure-app-backend\n3. Look for the email HTML content in the logs\n\nAlternatively, click "Go to Email Verification" to resend the verification email.`);
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
        <div>
          <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
            Sign in to your account
          </h2>
        </div>
        
        {error && (
          <div className="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg border border-red-200" role="alert">
            <div className="flex items-start">
              <svg className="w-4 h-4 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd"/>
              </svg>
              <div>
                {error}
              </div>
            </div>
          </div>
        )}

        {showVerificationPrompt && (
          <div className="p-4 mb-4 text-sm text-blue-700 bg-blue-100 rounded-lg border border-blue-200">
            <div className="flex items-start">
              <svg className="w-4 h-4 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd"/>
              </svg>
              <div>
                <p className="font-medium">Email Verification Required</p>
                <div className="mt-2 space-y-2">
                  <button
                    onClick={handleResendVerification}
                    className="text-blue-600 hover:text-blue-800 underline text-sm font-medium"
                  >
                    Go to Email Verification Page
                  </button>
                  <br />
                  <button
                    onClick={handleViewVerificationEmail}
                    className="text-blue-600 hover:text-blue-800 underline text-sm"
                  >
                    How to view verification emails (Development)
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
        
        <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
          <div className="rounded-md shadow-sm -space-y-px">
            <div>
              <label htmlFor="email-address" className="sr-only">Email address</label>
              <input
                id="email-address"
                name="email"
                type="email"
                autoComplete="email"
                required
                className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                placeholder="Email address"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>
            <div>
              <label htmlFor="password" className="sr-only">Password</label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                placeholder="Password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </div>
          </div>

          <div className="flex items-center justify-between">
            <div className="text-sm">
              <Link to="/forgot-password" className="font-medium text-indigo-600 hover:text-indigo-500">
                Forgot your password?
              </Link>
            </div>
            <div className="text-sm">
              <Link to="/register" className="font-medium text-indigo-600 hover:text-indigo-500">
                Create an account
              </Link>
            </div>
          </div>

          <div>
            <button
              type="submit"
              disabled={isLoading}
              className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? (
                <>
                  <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Signing in...
                </>
              ) : 'Sign in'}
            </button>
          </div>

          <div className="text-center">
            <div className="text-xs text-gray-500 mt-4 p-3 bg-gray-50 rounded">
              <p className="font-medium">Development Notice:</p>
              <p className="mt-1">Email verification emails are logged to the backend console. Use the "How to view verification emails" link above for instructions.</p>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
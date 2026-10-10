/**
 * WordPress REST API wrapper for ACA plugin
 */

// WordPress API helpers
declare global {
    interface Window {
        acaData: {
            nonce: string;
            api_url: string;
            admin_url: string;
            plugin_url: string;
        };
    }
}

const makeApiCall = async (path: string, options: RequestInit = {}) => {
    // Check if WordPress localized data is available
    if (!window.acaData) {
        console.error('ACA Error: window.acaData is not defined. Plugin scripts may not be loaded correctly.');
        throw new Error('WordPress data not available');
    }

    const defaultHeaders = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.acaData.nonce,
    };

    const mergedOptions = { ...options, headers: { ...defaultHeaders, ...options.headers } };
    
    const response = await fetch(`${window.acaData.api_url}${path}`, {
    ...mergedOptions,
  });

  if (!response.ok) {
    let errorMessage = `HTTP error! status: ${response.status}`;
    
    try {
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        const errorData = await response.json();
        errorMessage = errorData.message || JSON.stringify(errorData);
      } else {
        // Extract meaningful information from HTML responses
        const htmlText = await response.text();
        
        // Try to extract title from HTML
        const titleMatch = htmlText.match(/<title>(.*?)<\/title>/i);
        if (titleMatch && titleMatch[1]) {
          errorMessage = titleMatch[1].replace(/&[^;]+;/g, ''); // Remove HTML entities
        } else if (htmlText.includes('critical error') || htmlText.includes('fatal error')) {
          errorMessage = 'WordPress critical error occurred. Check server logs for details.';
        } else if (htmlText.includes('404')) {
          errorMessage = 'Requested resource not found (404)';
        } else if (htmlText.includes('500')) {
          errorMessage = 'Internal server error (500). Please try again later.';
        } else if (htmlText.includes('403')) {
          errorMessage = 'Access forbidden (403). Check your permissions.';
        } else {
          // Try to extract error message from common WordPress error patterns
          const errorMatch = htmlText.match(/<div[^>]*class="[^"]*error[^"]*"[^>]*>(.*?)<\/div>/i);
          if (errorMatch && errorMatch[1]) {
            errorMessage = errorMatch[1].replace(/<[^>]*>/g, '').trim(); // Remove HTML tags
          } else {
            errorMessage = `Server returned a non-JSON response (${response.status}). Please check server logs.`;
          }
        }
      }
    } catch (parseError) {
      errorMessage = `Failed to parse error response: ${response.status}. Network or server issue.`;
    }
    
    throw new Error(errorMessage);
  }

  return response.json();
}

// Settings API
export const settingsApi = {
  get: () => makeApiCall('settings'),
  save: (settings: any) => makeApiCall('settings', {
    method: 'POST',
    body: JSON.stringify(settings),
  }),
  update: (settings: any) => makeApiCall('settings', {
    method: 'POST',
    body: JSON.stringify(settings),
  }),
};

// Style Guide API
export const styleGuideApi = {
  get: () => makeApiCall('style-guide'),
  analyze: () => makeApiCall('style-guide/analyze', { method: 'POST' }),
  save: (styleGuide: any) => makeApiCall('style-guide', {
    method: 'POST',
    body: JSON.stringify(styleGuide),
  }),
  update: (styleGuide: any) => makeApiCall('style-guide', {
    method: 'POST',
    body: JSON.stringify(styleGuide),
  }),
};

// Ideas API
export const ideasApi = {
  get: () => makeApiCall('ideas'),
  generate: (count: number = 5) => makeApiCall('ideas/generate', {
    method: 'POST',
    body: JSON.stringify({ count }),
  }),
  generateSimilar: (ideaId: number) => makeApiCall('ideas/similar', {
    method: 'POST',
    body: JSON.stringify({ ideaId }),
  }),
  create: (idea: any) => makeApiCall('ideas', {
    method: 'POST',
    body: JSON.stringify(idea),
  }),
  update: (id: number, updates: any) => makeApiCall(`ideas/${id}`, {
    method: 'PUT',
    body: JSON.stringify(updates),
  }),
  delete: (id: number) => makeApiCall(`ideas/${id}`, { method: 'DELETE' }),
  restore: (id: number) => makeApiCall(`ideas/${id}/restore`, { method: 'POST' }),
  permanentDelete: (id: number) => makeApiCall(`ideas/${id}/permanent-delete`, { method: 'DELETE' }),
};

// Drafts API
export const draftsApi = {
  get: () => makeApiCall('drafts'),
  createFromIdea: (ideaId: number) => makeApiCall('drafts/create', {
    method: 'POST',
    body: JSON.stringify({ ideaId }),
  }),
  update: (id: number, updates: any) => makeApiCall(`drafts/${id}`, {
    method: 'PUT',
    body: JSON.stringify(updates),
  }),
  schedule: (id: number, scheduledDate: string) => makeApiCall(`drafts/${id}/schedule`, {
    method: 'POST',
    body: JSON.stringify({ scheduledDate }),
  }),
};

// Published posts API
export const publishedApi = {
  get: (page: number = 1, perPage: number = 20) => makeApiCall(`published?page=${encodeURIComponent(page)}&per_page=${encodeURIComponent(perPage)}`),
  publish: (draftId: number) => makeApiCall(`drafts/${draftId}/publish`, { 
    method: 'POST' 
  }),
  updateDate: (postId: number, newDate: string, shouldConvertToDraft: boolean = false) => makeApiCall(`published/${postId}/update-date`, {
    method: 'POST',
    body: JSON.stringify({ newDate, shouldConvertToDraft }),
  }),
};

// Activity logs API
export const activityApi = {
  get: () => makeApiCall('activity-logs'),
  create: (log: any) => makeApiCall('activity-logs', {
    method: 'POST',
    body: JSON.stringify(log),
  }),
};

// License API
export const licenseApi = {
  verify: async (licenseKey: string) => {
    try {
      const result = await makeApiCall('license/verify', {
        method: 'POST',
        body: JSON.stringify({ license_key: licenseKey }),
      });
      
      // Log the result for debugging
      console.log('License verification result:', result);
      
      return result;
    } catch (error) {
      console.error('License API error:', error);
      throw error;
    }
  },
  deactivate: async () => {
    try {
      const result = await makeApiCall('license/deactivate', {
        method: 'POST',
      });
      console.log('License deactivation result:', result);
      return result;
    } catch (error) {
      console.error('License deactivation error:', error);
      throw error;
    }
  },
  getStatus: () => makeApiCall('license/status'),
  refresh: () => makeApiCall('license/refresh', { method: 'POST' }),
};





// Image Generation API
export const imageApi = {
  generate: (prompt: string, style: string = 'digital_art') => makeApiCall('generate-image', {
    method: 'POST',
    body: JSON.stringify({ prompt, style }),
  }),
};

// Queue Management API
export const queueApi = {
  getStats: () => makeApiCall('automation/status'),
};

// Debug API
export const debugApi = {
  getAutomationStatus: () => makeApiCall('debug/automation'),
};
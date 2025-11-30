// Advanced Request System - Backend API
// CORS-safe implementation for Vercel

const ADVANCED_PROXIES = [
    'https://cors-anywhere.herokuapp.com/',
    'https://api.codetabs.com/v1/proxy?quest=',
    'https://corsproxy.io/?',
    'https://proxy.cors.sh/',
    'https://crossorigin.me/',
    'https://cors.bridged.cc/'
];

const USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'
];

class RequestSystem {
    constructor() {
        this.proxyIndex = 0;
        this.userAgentIndex = 0;
    }

    // Main request handler
    async sendAdvancedRequest(config) {
        const startTime = Date.now();
        
        try {
            let result;
            
            // Choose engine based on configuration
            if (config.engine === 'axios' || (config.engine === 'mixed' && Math.random() > 0.5)) {
                result = await this.sendWithAxios(config);
            } else {
                result = await this.sendWithFetch(config);
            }
            
            return {
                ...result,
                responseTime: Date.now() - startTime
            };
            
        } catch (error) {
            return {
                success: false,
                status: 0,
                status_text: 'SYSTEM_ERROR',
                response: error.message,
                responseTime: Date.now() - startTime
            };
        }
    }

    // Fetch-based request
    async sendWithFetch(config) {
        const url = await this.prepareUrl(config);
        const options = this.prepareFetchOptions(config);

        try {
            const response = await fetch(url, options);
            const responseText = await response.text();

            return {
                success: response.ok,
                status: response.status,
                status_text: this.getStatusText(response.status),
                response: responseText.substring(0, 500),
                size: responseText.length
            };
        } catch (error) {
            throw new Error(`Fetch failed: ${error.message}`);
        }
    }

    // Axios-based request
    async sendWithAxios(config) {
        const url = await this.prepareUrl(config);
        const options = this.prepareAxiosOptions(config);

        try {
            const response = await axios(url, options);
            
            return {
                success: response.status >= 200 && response.status < 300,
                status: response.status,
                status_text: this.getStatusText(response.status),
                response: typeof response.data === 'string' 
                    ? response.data.substring(0, 500)
                    : JSON.stringify(response.data).substring(0, 500),
                size: JSON.stringify(response.data).length
            };
        } catch (error) {
            if (error.response) {
                return {
                    success: false,
                    status: error.response.status,
                    status_text: this.getStatusText(error.response.status),
                    response: error.response.data ? JSON.stringify(error.response.data).substring(0, 500) : 'Error response',
                    size: 0
                };
            }
            throw new Error(`Axios failed: ${error.message}`);
        }
    }

    // Prepare URL with proxy if needed
    async prepareUrl(config) {
        if (!config.use_proxy) {
            return config.url;
        }

        // Rotate through proxies
        const proxy = ADVANCED_PROXIES[this.proxyIndex % ADVANCED_PROXIES.length];
        this.proxyIndex++;

        // Test proxy before using
        const isWorking = await this.testProxy(proxy);
        if (isWorking) {
            return proxy + encodeURIComponent(config.url);
        } else {
            // Fallback to direct request if proxy fails
            return config.url;
        }
    }

    // Test proxy functionality
    async testProxy(proxy) {
        try {
            const testUrl = proxy + encodeURIComponent('https://httpbin.org/get');
            const response = await fetch(testUrl, { 
                method: 'GET',
                signal: AbortSignal.timeout(5000)
            });
            return response.ok;
        } catch {
            return false;
        }
    }

    // Prepare fetch options
    prepareFetchOptions(config) {
        const headers = this.prepareHeaders(config);
        const options = {
            method: config.method,
            headers: headers,
            signal: config.signal
        };

        if (['POST', 'PUT'].includes(config.method) && config.data) {
            options.body = this.parseData(config.data, headers['Content-Type']);
        }

        return options;
    }

    // Prepare axios options
    prepareAxiosOptions(config) {
        const headers = this.prepareHeaders(config);
        return {
            method: config.method,
            headers: headers,
            signal: config.signal,
            data: ['POST', 'PUT'].includes(config.method) && config.data 
                ? this.parseData(config.data, headers['Content-Type'])
                : undefined,
            timeout: 10000,
            validateStatus: () => true // Don't throw on HTTP errors
        };
    }

    // Prepare headers with rotation
    prepareHeaders(config) {
        const baseHeaders = {
            'User-Agent': this.getNextUserAgent(),
            'Accept': '*/*',
            'Accept-Language': 'en-US,en;q=0.9',
            'Cache-Control': 'no-cache',
            'Connection': 'keep-alive'
        };

        // Add custom headers if provided
        try {
            if (config.headers) {
                const customHeaders = JSON.parse(config.headers);
                Object.assign(baseHeaders, customHeaders);
            }
        } catch (error) {
            console.warn('Invalid custom headers, using defaults');
        }

        // Add IP rotation headers if enabled
        if (config.rotate_ips) {
            baseHeaders['X-Forwarded-For'] = this.generateRandomIP();
            baseHeaders['X-Real-IP'] = this.generateRandomIP();
        }

        return baseHeaders;
    }

    // Get next user agent in rotation
    getNextUserAgent() {
        const agent = USER_AGENTS[this.userAgentIndex % USER_AGENTS.length];
        this.userAgentIndex++;
        return agent;
    }

    // Generate random IP for rotation
    generateRandomIP() {
        return `192.168.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}`;
    }

    // Parse data based on content type
    parseData(data, contentType) {
        if (contentType === 'application/json') {
            try {
                return JSON.stringify(JSON.parse(data));
            } catch {
                return data;
            }
        }
        return data;
    }

    // Get HTTP status text
    getStatusText(code) {
        const statusMap = {
            200: 'OK', 201: 'Created', 204: 'No Content',
            301: 'Moved Permanently', 302: 'Found', 304: 'Not Modified',
            400: 'Bad Request', 401: 'Unauthorized', 403: 'Forbidden',
            404: 'Not Found', 405: 'Method Not Allowed', 429: 'Too Many Requests',
            500: 'Internal Server Error', 502: 'Bad Gateway', 503: 'Service Unavailable'
        };
        return statusMap[code] || 'Unknown';
    }
}

// Create global system instance
const requestSystem = new RequestSystem();

// Export functions for frontend
export async function sendAdvancedRequest(config) {
    return await requestSystem.sendAdvancedRequest(config);
}

export async function executeAttack(config) {
    // This function handles the complete attack sequence
    const results = [];
    const requestsPerBatch = Math.ceil(config.jumlah / config.concurrent);
    
    for (let i = 0; i < config.concurrent; i++) {
        const start = i * requestsPerBatch;
        const end = Math.min(start + requestsPerBatch, config.jumlah);
        
        if (start < config.jumlah) {
            const batchResults = await executeBatch(config, start, end);
            results.push(...batchResults);
        }
    }
    
    return results;
}

async function executeBatch(config, start, end) {
    const results = [];
    
    for (let i = start; i < end; i++) {
        if (config.signal?.aborted) break;
        
        try {
            const result = await requestSystem.sendAdvancedRequest({
                ...config,
                requestId: i + 1
            });
            results.push(result);
            
            // Add delay if specified
            if (i < end - 1 && config.delay > 0) {
                await new Promise(resolve => setTimeout(resolve, config.delay));
            }
        } catch (error) {
            results.push({
                success: false,
                status: 0,
                status_text: 'BATCH_ERROR',
                response: error.message,
                responseTime: 0
            });
        }
    }
    
    return results;
}

// Additional utility functions
export function getSystemInfo() {
    return {
        proxies: ADVANCED_PROXIES.length,
        userAgents: USER_AGENTS.length,
        features: ['axios', 'fetch', 'proxy_rotation', 'ip_rotation', 'cors_bypass']
    };
}

export function testConnection(url) {
    return requestSystem.sendAdvancedRequest({
        url: url,
        method: 'GET',
        use_proxy: true,
        engine: 'mixed'
    });
}

// Initialize system
console.log('Advanced Request System initialized');
console.log('Proxies available:', ADVANCED_PROXIES.length);
console.log('User agents available:', USER_AGENTS.length);
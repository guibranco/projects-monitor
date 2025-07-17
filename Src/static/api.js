// api.js
import { API_ENDPOINTS } from './constants.js';
import { NotificationManager } from './ui.js';

export class ApiManager {
  constructor() {
    this.isSessionInvalid = false;
  }

  /**
   * Loads data from a specified URL using XMLHttpRequest.
   *
   * This function checks if the session is valid before making the API call.
   * If the session is invalid, it logs a warning and aborts the request.
   * Upon receiving a response, it processes the JSON data or shows a login modal
   * if an authentication error (401 or 403) occurs.
   *
   * @param {string} url - The URL from which to load data.
   * @param {function} callback - The function to be called with the parsed response data.
   */
  load(url, callback) {
    if (this.isSessionInvalid) {
      console.warn("Session is invalid. API call aborted.");
      return;
    }

    const xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.onreadystatechange = () => {
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          callback(JSON.parse(xhr.responseText));
        } else if (xhr.status === 401 || xhr.status === 403) {
          if (this.isSessionInvalid === false) {
            this.isSessionInvalid = true;
            this.showLoginModal();
          }
        }
      }
    };
    xhr.send();
  }

  /**
   * Displays a login modal with session expired message and login options.
   */
  showLoginModal() {
    const modal = document.createElement("div");
    modal.classList.add("modal-backdrop");
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.setAttribute("aria-labelledby", "modalTitle");

    const modalContent = document.createElement("div");
    modalContent.classList.add("modal-content");
    modalContent.innerHTML = `
      <h2 id="modalTitle">Session Expired</h2>
      <p>Your session has expired. Please login again.</p>
      <button id="cancelBtn">Cancel</button>
      <button id="loginBtn">Login</button>
    `;

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    document.getElementById("cancelBtn").addEventListener("click", () => {
      modal.remove();
    });

    document.getElementById("loginBtn").addEventListener("click", () => {
      window.location.href = "login.php";
    });
  }

  /**
   * Deletes a message by application.
   *
   * This function sends a POST request to the messages delete endpoint with the specified application.
   * It handles the response and shows a success notification if successful. If an error occurs,
   * it displays an error notification and rethrows the error.
   */
  async deleteMessageByApplication(application) {
    try {
      const response = await fetch(API_ENDPOINTS.MESSAGES_DELETE, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ application }),
      });

      if (!response.ok) {
        throw new Error("Network response was not ok");
      }

      const data = await response.json();
      NotificationManager.show("Success", "Item was successfully deleted", "success");
      return data;
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to delete item: ${error.message}`,
        "error"
      );
      throw error;
    }
  }

  /**
   * Deletes an error log file from a specified directory using the cPanel API.
   * This function makes an asynchronous POST request to delete the file and handles
   * responses, showing success or error notifications accordingly.
   *
   * @param {string} directory - The path to the directory containing the error log file.
   */
  async deleteErrorLogFile(directory) {
    try {
      const response = await fetch(API_ENDPOINTS.CPANEL_DELETE, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ directory }),
      });

      if (!response.ok) {
        throw new Error("Network response was not ok");
      }

      const data = await response.json();
      NotificationManager.show("Success", "Item was successfully deleted", "success");
      return data;
    } catch (error) {
      NotificationManager.show(
        "Error",
        `Failed to delete item: ${error.message}`,
        "error"
      );
      throw error;
    }
  }
}

export class DataLoader {
  constructor(apiManager) {
    this.apiManager = apiManager;
  }

  /**
   * Loads data from various endpoints and displays it using corresponding window functions.
   */
  load30Interval() {
    this.apiManager.load(API_ENDPOINTS.APPVEYOR, (data) => window.showAppVeyor?.(data));
    this.apiManager.load(API_ENDPOINTS.CPANEL, (data) => window.showCPanel?.(data));
    this.apiManager.load(API_ENDPOINTS.MESSAGES, (data) => window.showMessages?.(data));
    this.apiManager.load(API_ENDPOINTS.QUEUES, (data) => window.showQueues?.(data));
    
    const feedState = window.feedState || { filter: 'all' };
    const workflowLimiterState = window.workflowLimiterState || { enabled: false, limitValue: 10 };
    
    this.apiManager.load(
      `${API_ENDPOINTS.WEBHOOKS}?feedOptionsFilter=${feedState.filter}&workflowsLimiterEnabled=${workflowLimiterState.enabled}&workflowsLimiterQuantity=${workflowLimiterState.limitValue}`,
      (data) => window.showWebhook?.(data)
    );
  }

  /**
   * Loads data from various API endpoints and displays it using corresponding show functions.
   */
  load60Interval() {
    this.apiManager.load(API_ENDPOINTS.DOMAINS, (data) => window.showDomains?.(data));
    this.apiManager.load(API_ENDPOINTS.GITHUB, (data) => window.showGitHub?.(data));
    this.apiManager.load(API_ENDPOINTS.HEALTHCHECKS, (data) => window.showHealthChecksIo?.(data));
    this.apiManager.load(API_ENDPOINTS.UPTIMEROBOT, (data) => window.showUpTimeRobot?.(data));
    this.apiManager.load(API_ENDPOINTS.WIREGUARD, (data) => window.showWireGuard?.(data));
  }

  /**
   * Loads data from POSTMAN endpoint and shows it in the postman window.
   */
  load300Interval() {
    this.apiManager.load(API_ENDPOINTS.POSTMAN, (data) => window.showPostman?.(data));
  }
}
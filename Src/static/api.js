// api.js
import { API_ENDPOINTS } from './constants.js';
import { NotificationManager } from './ui.js';

export class ApiManager {
  constructor() {
    this.isSessionInvalid = false;
  }

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

  load60Interval() {
    this.apiManager.load(API_ENDPOINTS.DOMAINS, (data) => window.showDomains?.(data));
    this.apiManager.load(API_ENDPOINTS.GITHUB, (data) => window.showGitHub?.(data));
    this.apiManager.load(API_ENDPOINTS.HEALTHCHECKS, (data) => window.showHealthChecksIo?.(data));
    this.apiManager.load(API_ENDPOINTS.UPTIMEROBOT, (data) => window.showUpTimeRobot?.(data));
    this.apiManager.load(API_ENDPOINTS.WIREGUARD, (data) => window.showWireGuard?.(data));
  }

  load300Interval() {
    this.apiManager.load(API_ENDPOINTS.POSTMAN, (data) => window.showPostman?.(data));
  }
}
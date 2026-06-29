function getWebhookUrl() {
  const host = window.location.hostname;
  const isLocal = host === "localhost" || host === "127.0.0.1" || host === "";

  if (isLocal) {
    return "/publiaisolucoes/publi-ai-solucoes/crm/api/leads.php";
  }

  return "/crm/api/leads.php";
}

const leadForm = document.querySelector("#leadForm");
const formSuccess = document.querySelector("#formSuccess");
const modal = document.querySelector("#lead-modal");
const openButtons = document.querySelectorAll("[data-open-form]");
const closeButtons = document.querySelectorAll("[data-close-form]");
const steps = Array.from(document.querySelectorAll(".wizard-step"));
const nextStepButton = document.querySelector("#nextStep");
const prevStepButton = document.querySelector("#prevStep");
const submitButton = document.querySelector("#submitLead");
const progressBar = document.querySelector("#progressBar");
const stepCounter = document.querySelector("#stepCounter");

let currentStep = 0;

function getTrackingParams() {
  const params = new URLSearchParams(window.location.search);

  return {
    utm_source: params.get("utm_source") || "",
    utm_medium: params.get("utm_medium") || "",
    utm_campaign: params.get("utm_campaign") || "",
    utm_content: params.get("utm_content") || "",
    utm_term: params.get("utm_term") || "",
    referrer: document.referrer || "",
    landing_path: `${window.location.pathname}${window.location.search}`,
  };
}

function getFormPayload(form) {
  const data = new FormData(form);

  return {
    name: data.get("name"),
    whatsapp: data.get("whatsapp"),
    company: data.get("company"),
    segment: data.get("segment"),
    advertises: data.get("advertises"),
    message: data.get("message"),
    website: data.get("website") || "",
    page: window.location.href,
    ...getTrackingParams(),
    submittedAt: new Date().toISOString(),
  };
}

function updateWizard() {
  steps.forEach((step, index) => {
    step.classList.toggle("is-active", index === currentStep);
  });

  const progress = ((currentStep + 1) / steps.length) * 100;
  progressBar.style.width = `${progress}%`;
  stepCounter.textContent = `Pergunta ${currentStep + 1} de ${steps.length}`;
  prevStepButton.disabled = currentStep === 0;
  leadForm.classList.toggle("is-last-step", currentStep === steps.length - 1);

  const activeField = steps[currentStep].querySelector("input, select, textarea");
  activeField?.focus();
}

function validateCurrentStep() {
  const fields = Array.from(steps[currentStep].querySelectorAll("input, select, textarea"));
  return fields.every((field) => field.reportValidity());
}

function openModal(event) {
  event.preventDefault();
  modal.classList.add("is-open");
  modal.setAttribute("aria-hidden", "false");
  document.body.classList.add("modal-open");
  currentStep = 0;
  leadForm.classList.remove("is-submitted");
  leadForm.reset();
  formSuccess.classList.remove("is-visible");
  updateWizard();
}

function closeModal() {
  modal.classList.remove("is-open");
  modal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("modal-open");
}

async function sendLeadToWebhook(payload) {
  const webhookUrl = getWebhookUrl();

  if (!webhookUrl) {
    throw new Error("Endpoint do CRM não configurado.");
  }

  // CRM: enviar o payload para o webhook/API que cria o lead no CRM.
  // WhatsApp/e-mail: o backend ou automacao deve disparar a notificacao ao receber este payload.
  // Pixel/tag de conversao: disparar evento de conversao apos resposta positiva da API.
  const response = await fetch(webhookUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(`Falha ao enviar lead: ${response.status} ${errorText}`);
  }
}

openButtons.forEach((button) => {
  button.addEventListener("click", openModal);
});

closeButtons.forEach((button) => {
  button.addEventListener("click", closeModal);
});

nextStepButton.addEventListener("click", () => {
  if (!validateCurrentStep()) {
    return;
  }

  currentStep = Math.min(currentStep + 1, steps.length - 1);
  updateWizard();
});

prevStepButton.addEventListener("click", () => {
  currentStep = Math.max(currentStep - 1, 0);
  updateWizard();
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && modal.classList.contains("is-open")) {
    closeModal();
  }
});

leadForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  if (!validateCurrentStep()) {
    return;
  }

  const payload = getFormPayload(leadForm);

  submitButton.disabled = true;
  submitButton.textContent = "Enviando...";

  try {
    await sendLeadToWebhook(payload);
    leadForm.reset();
    currentStep = 0;
    updateWizard();
    leadForm.classList.add("is-submitted");
    formSuccess.classList.add("is-visible");

    // Conversao: chamar aqui eventos como gtag('event', 'generate_lead') ou fbq('track', 'Lead').
  } catch (error) {
    alert("Nao foi possivel enviar agora. Tente novamente em instantes.");
    console.error(error);
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = "Solicitar demonstração";
  }
});

updateWizard();

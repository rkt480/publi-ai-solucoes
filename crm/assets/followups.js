const stepsContainer = document.querySelector("#flowSteps");
const addStepButton = document.querySelector("#addFlowStep");
const template = document.querySelector("#flowStepTemplate");
const flowForm = document.querySelector("#flowForm");
const flowId = document.querySelector("#flowId");
const stepsJson = document.querySelector("#stepsJson");
const flowName = document.querySelector("#flowName");
const flowDescription = document.querySelector("#flowDescription");
const saveFlowButton = document.querySelector("#saveFlowButton");
const cancelEditButton = document.querySelector("#cancelEditFlow");
const csrfToken = document.querySelector("meta[name='csrf-token']")?.content || "";

function splitMinutes(minutes) {
  if (minutes >= 1440 && minutes % 1440 === 0) {
    return { value: minutes / 1440, unit: "days" };
  }

  if (minutes >= 60 && minutes % 60 === 0) {
    return { value: minutes / 60, unit: "hours" };
  }

  return { value: minutes, unit: "minutes" };
}

function createStep(stepData = { delay_minutes: 0, message: "" }) {
  const clone = template.content.firstElementChild.cloneNode(true);
  const delay = splitMinutes(Number(stepData.delay_minutes || 0));

  clone.querySelector("[data-name='delay_value']").value = delay.value;
  clone.querySelector("[data-name='delay_unit']").value = delay.unit;
  clone.querySelector("[data-name='message']").value = stepData.message || "";
  bindRemoveButton(clone);

  return clone;
}

function refreshStepNames() {
  const steps = Array.from(stepsContainer.querySelectorAll("[data-step]"));

  steps.forEach((step, index) => {
    step.querySelector("strong").textContent = `Mensagem ${index + 1}`;
    step.querySelectorAll("[name], [data-name]").forEach((field) => {
      const key = field.dataset.name || field.name.match(/\[(.*?)\]$/)?.[1];

      if (key) {
        field.name = `steps[${index}][${key}]`;
      }
    });

    const removeButton = step.querySelector("[data-remove-step]");
    removeButton.disabled = steps.length === 1;
  });

  syncStepsJson();
}

function bindRemoveButton(step) {
  step.querySelector("[data-remove-step]").addEventListener("click", () => {
    const steps = stepsContainer.querySelectorAll("[data-step]");

    if (steps.length <= 1) {
      return;
    }

    step.remove();
    refreshStepNames();
  });
}

function syncStepsJson() {
  if (!stepsJson) {
    return;
  }

  const steps = Array.from(stepsContainer.querySelectorAll("[data-step]")).map((step) => {
    const delayValueField = step.querySelector("[data-name='delay_value'], [name$='[delay_value]']");
    const delayUnitField = step.querySelector("[data-name='delay_unit'], [name$='[delay_unit]']");
    const messageField = step.querySelector("[data-name='message'], [name$='[message]']");

    return {
      delay_value: Number(delayValueField?.value || 0),
      delay_unit: String(delayUnitField?.value || "minutes"),
      message: String(messageField?.value || ""),
    };
  });

  stepsJson.value = JSON.stringify(steps);
}

stepsContainer.querySelectorAll("[data-step]").forEach(bindRemoveButton);

addStepButton.addEventListener("click", () => {
  const clone = createStep({ delay_minutes: 1440, message: "" });
  stepsContainer.appendChild(clone);
  refreshStepNames();
  clone.querySelector("textarea")?.focus();
});

stepsContainer.addEventListener("input", syncStepsJson);
stepsContainer.addEventListener("change", syncStepsJson);

document.querySelectorAll("[data-edit-flow]").forEach((button) => {
  button.addEventListener("click", () => {
    const item = button.closest(".flow-item");
    const flow = JSON.parse(item.dataset.flow || "{}");

    flowId.value = flow.id || "";
    flowName.value = flow.name || "";
    flowDescription.value = flow.description || "";
    stepsContainer.innerHTML = "";

    const steps = Array.isArray(flow.steps) && flow.steps.length > 0
      ? flow.steps
      : [{ delay_minutes: 0, message: "" }];

    steps.forEach((step) => {
      stepsContainer.appendChild(createStep(step));
    });

    saveFlowButton.textContent = "Salvar alterações";
    cancelEditButton.hidden = false;
    refreshStepNames();
    flowForm.scrollIntoView({ behavior: "smooth", block: "start" });
  });
});

cancelEditButton.addEventListener("click", () => {
  flowForm.reset();
  flowId.value = "";
  stepsContainer.innerHTML = "";
  stepsContainer.appendChild(createStep({ delay_minutes: 0, message: "" }));
  saveFlowButton.textContent = "Salvar fluxo";
  cancelEditButton.hidden = true;
  refreshStepNames();
});

flowForm.addEventListener("submit", () => {
  syncStepsJson();
});

async function runDueFollowups() {
  try {
    const response = await fetch("./run-followups.php?ajax=1", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-CSRF-Token": csrfToken,
      },
      body: new URLSearchParams({ ajax: "1" }),
    });

    if (!response.ok) {
      return;
    }

    await response.json();
  } catch (error) {
    console.error("Falha ao processar follow-ups.", error);
  }
}

refreshStepNames();
syncStepsJson();
runDueFollowups();
setInterval(runDueFollowups, 60000);

const cards = document.querySelectorAll(".kanban-card");
const dropzones = document.querySelectorAll(".kanban-dropzone");
const detailButtons = document.querySelectorAll("[data-toggle-details]");
const csrfToken = document.querySelector("meta[name='csrf-token']")?.content || "";

let draggedCard = null;
const modalOrigins = new WeakMap();

function updateColumnCounts() {
  document.querySelectorAll(".kanban-column").forEach((column) => {
    const count = column.querySelectorAll(".kanban-card").length;
    const counter = column.querySelector(".kanban-column-header strong");

    if (counter) {
      counter.textContent = count;
    }
  });
}

async function persistLeadStatus(leadId, status) {
  const response = await fetch("./api/update-status.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": csrfToken,
    },
    body: JSON.stringify({ id: leadId, status }),
  });

  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || "Não foi possível mover o lead.");
  }
}

cards.forEach((card) => {
  card.addEventListener("dragstart", () => {
    draggedCard = card;
    card.classList.add("is-dragging");
  });

  card.addEventListener("dragend", () => {
    card.classList.remove("is-dragging");
    draggedCard = null;
  });
});

dropzones.forEach((zone) => {
  zone.addEventListener("dragover", (event) => {
    event.preventDefault();
    zone.classList.add("is-over");
  });

  zone.addEventListener("dragleave", () => {
    zone.classList.remove("is-over");
  });

  zone.addEventListener("drop", async () => {
    zone.classList.remove("is-over");

    if (!draggedCard) {
      return;
    }

    const previousParent = draggedCard.parentElement;
    const targetStatus = zone.dataset.status;
    const leadId = draggedCard.dataset.leadId;

    zone.appendChild(draggedCard);
    updateColumnCounts();

    try {
      await persistLeadStatus(leadId, targetStatus);
      const statusInput = draggedCard.querySelector("input[name='status']");

      if (statusInput) {
        statusInput.value = targetStatus;
      }
    } catch (error) {
      previousParent.appendChild(draggedCard);
      updateColumnCounts();
      alert("Não foi possível mover o lead. Tente novamente.");
      console.error(error);
    }
  });
});

function findModalCard(panel) {
  const storedCard = modalOrigins.get(panel);

  if (storedCard) {
    return storedCard;
  }

  const leadId = panel.dataset.modalLeadId;
  return leadId ? document.querySelector(`.kanban-card[data-lead-id="${leadId}"]`) : null;
}

function closeLeadModal(panel) {
  const card = findModalCard(panel);

  panel.hidden = true;
  document.body.classList.remove("modal-open");

  if (card) {
    card.classList.remove("has-open-modal");
    card.appendChild(panel);

    const cardButton = card.querySelector(".lead-actions [data-toggle-details]");

    if (cardButton) {
      cardButton.textContent = "Detalhes";
    }
  }
}

function openLeadModal(card, panel, button) {
  document.querySelectorAll(".lead-details-panel:not([hidden])").forEach(closeLeadModal);

  modalOrigins.set(panel, card);
  document.body.appendChild(panel);
  panel.hidden = false;
  card.classList.add("has-open-modal");
  document.body.classList.add("modal-open");
  button.textContent = "Ocultar";
}

detailButtons.forEach((button) => {
  button.addEventListener("click", () => {
    const panel = button.closest(".lead-details-panel");

    if (button.classList.contains("modal-close") && panel) {
      closeLeadModal(panel);
      return;
    }

    const card = button.closest(".kanban-card");
    const cardPanel = card?.querySelector(".lead-details-panel");

    if (!card || !cardPanel) {
      return;
    }

    if (cardPanel.hidden) {
      openLeadModal(card, cardPanel, button);
    } else {
      closeLeadModal(cardPanel);
    }
  });
});

document.querySelectorAll(".lead-details-panel").forEach((panel) => {
  panel.addEventListener("click", (event) => {
    if (event.target !== panel) {
      return;
    }

    closeLeadModal(panel);
  });
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") {
    return;
  }

  document.querySelectorAll(".lead-details-panel:not([hidden])").forEach((panel) => {
    closeLeadModal(panel);
  });
});

document.querySelectorAll(".lead-modal-tabs [data-lead-tab]").forEach((tabButton) => {
  tabButton.addEventListener("click", () => {
    const modal = tabButton.closest(".lead-modal-card");
    const target = tabButton.dataset.leadTab;

    if (!modal || !target) {
      return;
    }

    modal.querySelectorAll("[data-lead-tab]").forEach((button) => {
      button.classList.toggle("active", button === tabButton);
    });

    modal.querySelectorAll("[data-lead-panel]").forEach((panel) => {
      panel.hidden = panel.dataset.leadPanel !== target;
      panel.classList.toggle("active", panel.dataset.leadPanel === target);
    });
  });
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

runDueFollowups();
setInterval(runDueFollowups, 60000);

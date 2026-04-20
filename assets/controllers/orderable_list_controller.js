import { Controller } from '@hotwired/stimulus';

/*
 * Simple drag-and-drop list ordering.
 * Reorders <li data-orderable-list-target="item"> elements by mouse drag.
 * After each reorder, rebuilds the hidden-input form field `orderedUserIds[]`
 * so the form submission reflects the current order.
 */
export default class extends Controller {
    static targets = ['list', 'item', 'hiddenContainer'];

    connect() {
        this.itemTargets.forEach((item) => this.attach(item));
        this.sync();
    }

    attach(item) {
        item.addEventListener('dragstart', (event) => this.onDragStart(event));
        item.addEventListener('dragover', (event) => this.onDragOver(event));
        item.addEventListener('drop', (event) => this.onDrop(event));
        item.addEventListener('dragend', () => this.onDragEnd());
    }

    onDragStart(event) {
        this.dragged = event.currentTarget;
        this.dragged.classList.add('opacity-50');
        event.dataTransfer.effectAllowed = 'move';
    }

    onDragOver(event) {
        event.preventDefault();
        const target = event.currentTarget;
        if (!this.dragged || target === this.dragged) {
            return;
        }

        const list = target.parentElement;
        if (list !== this.dragged.parentElement) {
            return;
        }

        const rect = target.getBoundingClientRect();
        const middle = rect.top + rect.height / 2;
        if (event.clientY < middle) {
            list.insertBefore(this.dragged, target);
        } else {
            list.insertBefore(this.dragged, target.nextSibling);
        }
    }

    onDrop(event) {
        event.preventDefault();
    }

    onDragEnd() {
        if (this.dragged) {
            this.dragged.classList.remove('opacity-50');
            this.dragged = null;
        }
        this.sync();
    }

    sync() {
        if (!this.hasHiddenContainerTarget) {
            return;
        }

        const userIds = [];
        this.listTargets.forEach((list) => {
            list.querySelectorAll('[data-user-id]').forEach((item) => {
                userIds.push(item.dataset.userId);
            });
        });

        const container = this.hiddenContainerTarget;
        container.innerHTML = '';

        userIds.forEach((userId, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `resolve_ties_form[orderedUserIds][${index}]`;
            input.value = userId;
            container.appendChild(input);
        });
    }
}

const template = document.createElement('template');
template.innerHTML = `
<style>
  :host {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.15s;
  }
  :host(:hover) {
    background: #f7fafc;
  }
  .checkbox {
    width: 1.25rem;
    height: 1.25rem;
    border: 2px solid #cbd5e0;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    flex-shrink: 0;
  }
  .checkbox.checked {
    background: #48bb78;
    border-color: #48bb78;
  }
  .checkbox.checked::after {
    content: '\\2713';
    color: white;
    font-size: 0.75rem;
  }
  .title {
    flex: 1;
    font-size: 0.95rem;
    color: #2d3748;
  }
  .title.completed {
    text-decoration: line-through;
    color: #a0aec0;
  }
  .delete {
    opacity: 0;
    border: none;
    background: none;
    color: #e53e3e;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 0.25rem;
    transition: opacity 0.15s;
  }
  :host(:hover) .delete {
    opacity: 1;
  }
</style>
<div class="checkbox"></div>
<span class="title"></span>
<button class="delete" title="Delete">&#x2715;</button>
`;

class TodoItem extends HTMLElement {
  static get observedAttributes() {
    return ['todo-id', 'title', 'completed'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this.shadowRoot.appendChild(template.content.cloneNode(true));
  }

  connectedCallback() {
    this.shadowRoot.querySelector('.checkbox').addEventListener('click', () => {
      this.dispatchEvent(new CustomEvent('todo-toggle', {
        detail: { id: this.getAttribute('todo-id'), completed: this.getAttribute('completed') !== 'true' },
        bubbles: true, composed: true,
      }));
    });

    this.shadowRoot.querySelector('.delete').addEventListener('click', () => {
      this.dispatchEvent(new CustomEvent('todo-delete', {
        detail: { id: this.getAttribute('todo-id') },
        bubbles: true, composed: true,
      }));
    });

    this._render();
  }

  attributeChangedCallback() {
    this._render();
  }

  _render() {
    const title = this.getAttribute('title') || '';
    const completed = this.getAttribute('completed') === 'true';

    const checkbox = this.shadowRoot.querySelector('.checkbox');
    const titleEl = this.shadowRoot.querySelector('.title');

    titleEl.textContent = title;
    titleEl.classList.toggle('completed', completed);
    checkbox.classList.toggle('checked', completed);
  }
}

customElements.define('todo-item', TodoItem);
export default TodoItem;

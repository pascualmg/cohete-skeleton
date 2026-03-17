import './TodoItem.js';

const template = document.createElement('template');
template.innerHTML = `
<style>
  :host {
    display: block;
    max-width: 32rem;
    margin: 2rem auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }
  h1 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
    margin: 0 0 1.5rem;
  }
  .input-row {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
  }
  input {
    flex: 1;
    padding: 0.625rem 0.875rem;
    border: 2px solid #e2e8f0;
    border-radius: 0.5rem;
    font-size: 0.95rem;
    outline: none;
    transition: border-color 0.15s;
  }
  input:focus {
    border-color: #4299e1;
  }
  button {
    padding: 0.625rem 1.25rem;
    background: #4299e1;
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-size: 0.95rem;
    cursor: pointer;
    transition: background 0.15s;
  }
  button:hover {
    background: #3182ce;
  }
  button:disabled {
    background: #a0aec0;
    cursor: not-allowed;
  }
  .list {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    overflow: hidden;
  }
  .empty {
    padding: 2rem;
    text-align: center;
    color: #a0aec0;
    font-size: 0.9rem;
  }
  .status {
    margin-top: 0.75rem;
    font-size: 0.8rem;
    color: #a0aec0;
  }
</style>
<h1>Cohete Todo</h1>
<div class="input-row">
  <input type="text" placeholder="What needs to be done?" />
  <button>Add</button>
</div>
<div class="list"></div>
<div class="status"></div>
`;

class TodoApp extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this.shadowRoot.appendChild(template.content.cloneNode(true));
    this._todos = [];
  }

  connectedCallback() {
    const input = this.shadowRoot.querySelector('input');
    const btn = this.shadowRoot.querySelector('button');

    btn.addEventListener('click', () => this._add(input));
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') this._add(input);
    });

    this.shadowRoot.addEventListener('todo-toggle', (e) => this._toggle(e.detail));
    this.shadowRoot.addEventListener('todo-delete', (e) => this._delete(e.detail));

    this._fetchAll();
  }

  async _fetchAll() {
    const res = await fetch('/todos');
    this._todos = await res.json();
    this._render();
  }

  async _add(input) {
    const title = input.value.trim();
    if (!title) return;

    input.disabled = true;
    const res = await fetch('/todos', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title }),
    });
    const todo = await res.json();
    this._todos.push(todo);
    input.value = '';
    input.disabled = false;
    input.focus();
    this._render();
  }

  async _toggle(detail) {
    await fetch(`/todos/${detail.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ completed: detail.completed }),
    });
    const todo = this._todos.find(t => t.id === detail.id);
    if (todo) todo.completed = detail.completed;
    this._render();
  }

  async _delete(detail) {
    await fetch(`/todos/${detail.id}`, { method: 'DELETE' });
    this._todos = this._todos.filter(t => t.id !== detail.id);
    this._render();
  }

  _render() {
    const list = this.shadowRoot.querySelector('.list');
    const status = this.shadowRoot.querySelector('.status');

    if (this._todos.length === 0) {
      list.innerHTML = '<div class="empty">No todos yet. Add one above.</div>';
    } else {
      list.innerHTML = this._todos.map(t =>
        `<todo-item todo-id="${t.id}" title="${this._esc(t.title)}" completed="${t.completed}"></todo-item>`
      ).join('');
    }

    const done = this._todos.filter(t => t.completed).length;
    status.textContent = this._todos.length > 0
      ? `${done}/${this._todos.length} completed`
      : '';
  }

  _esc(s) {
    return s.replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }
}

customElements.define('todo-app', TodoApp);
export default TodoApp;

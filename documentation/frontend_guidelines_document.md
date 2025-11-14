# Frontend Guideline Document for TokoKiara

This document lays out the frontend setup, design principles, and technologies used in the TokoKiara application. It’s written in clear, everyday language so anyone can understand how the frontend is built and maintained.

## 1. Frontend Architecture

### Frameworks and Libraries
- **React.js**: The main library for building the user interface in a component-based way.
- **Inertia.js**: Bridges Laravel (backend) and React (frontend) to create a single-page-app feel without a separate API project.
- **MUI (Material-UI)**: Provides a set of ready-made React components following Material Design guidelines.
- **Tailwind CSS**: A utility-first CSS framework for rapid, consistent styling.
- **Vite**: Handles the dev server and production builds quickly and efficiently.
- **Zustand**: A lightweight state-management library for sharing data across components.
- **Axios**: For making HTTP requests to Laravel controllers.
- **TinyMCE**: Rich text editor for editing product descriptions and other content.
- **JsBarcode & Mustache**: For generating and templating barcodes.
- **Dexie (IndexedDB)**: Caches data locally for offline support and faster lookups.
- **Dayjs**: Simple date and time utilities.
- **Browser-Image-Compression**: Compresses images on the client before upload.

### Scalability, Maintainability, Performance
- **Component-based**: Breaking the UI into small, reusable parts makes it easy to add new features and fix bugs.
- **Single-page experience**: Inertia.js avoids full page reloads, improving responsiveness.
- **Utility-first CSS**: Tailwind keeps styles consistent and small, supporting fast loading.
- **Code splitting**: Vite automatically splits bundles so users only load the code they need.
- **Local caching**: Dexie stores key data in the browser, reducing server round-trips and enabling offline use.

## 2. Design Principles

- **Usability**: Simple, intuitive interfaces—forms and controls follow familiar patterns.
- **Accessibility**: Components meet WCAG standards (ARIA roles, keyboard navigation, sufficient color contrast).
- **Responsiveness**: Layouts and components adapt to phones, tablets, and desktops using Flexbox, Grid, and Tailwind breakpoints.
- **Consistency**: MUI and Tailwind ensure a unified look across all pages.
- **Feedback**: Loading spinners, success/error messages, and form validations keep users informed.

### Applying the Principles
- Buttons and links have clear labels and hover/focus states.
- Forms use proper labels, placeholders, and error messages.
- Color choices meet contrast ratios for readability.
- Layout adjusts gracefully from small to large screens, hiding or reorganizing less-important elements.

## 3. Styling and Theming

### Styling Approach
- **Tailwind CSS**: Utility classes (e.g., `px-4`, `text-gray-800`) for fast, consistent styling without custom CSS files.
- **MUI Theme**: Central theme object customizing primary/secondary colors, typography, and component defaults.

### Theming and Look & Feel
- Style: Modern Material Design with a flat look and subtle depth (shadows, smooth corners).
- Glassmorphism accents on cards or modals (semi-transparent backgrounds with blur) for a modern touch.

### Color Palette
- Primary: #1976D2 (blue)
- Secondary: #FF5722 (deep orange)
- Accent: #009688 (teal)
- Background: #F5F5F5 (light gray)
- Surface (cards, panels): #FFFFFF (white)
- Text Primary: #212121 (dark gray)
- Text Secondary: #757575 (medium gray)
- Success: #4CAF50 (green)
- Warning: #FFC107 (amber)
- Error: #F44336 (red)

### Typography
- Font: Roboto (Google font), used for all headings and body text
- Headings: bold weight (500–700)
- Body: regular weight (400)

## 4. Component Structure

- **`resources/js/Components`**: Reusable UI parts (buttons, dialogs, search boxes).
- **`resources/js/Pages`**: Page-level components tied to Laravel routes (rendered via Inertia).
- **Layouts**: `AuthenticatedLayout.jsx` wraps protected pages with navigation, header, and footer.
- **Utilities**: Helper hooks and functions (e.g., date formatting with Dayjs).

Why it matters: Component-based architecture lets you update or replace one part without touching the rest, speeding up development and reducing bugs.

## 5. State Management

- **Zustand**: Creates small, focused stores (e.g., currency settings) that components can read from or write to.
- **Local State**: For simple form inputs or temporary UI states, React’s `useState` is used.

How data flows:
1. Component reads state from a Zustand store or its own `useState`.
2. User actions dispatch updates to the store or local state.
3. Subscribed components re-render with the new data, keeping the UI in sync.

## 6. Routing and Navigation

- **Inertia.js**: Handles navigation by intercepting links and form submissions, sending them to Laravel, and updating React pages without a full reload.
- **Laravel Routes**: Defined in `routes/web.php`, each route returns an Inertia page.

Navigation structure:
- Top navigation bar (AppBar) with main sections: Sales, Inventory, Reports, Contacts, Settings.
- Side menus or tabs within each section for related features.
- Breadcrumbs for deeper pages to help users track where they are.

## 7. Performance Optimization

- **Lazy Loading**: Heavy components (e.g., large charts or editors) are loaded on demand using React.lazy and Suspense.
- **Code Splitting**: Vite automatically splits code into smaller chunks for faster initial loads.
- **Asset Optimization**: Images compressed client-side, SVG icons inlined, fonts served with `font-display: swap`.
- **Caching**: Dexie caches frequent data, reducing repeated network requests.
- **Minification**: Production builds include minified JavaScript and CSS.

## 8. Testing and Quality Assurance

- **Unit Tests**: Jest + React Testing Library for components (checking render output, user interactions).
- **Integration Tests**: Testing how multiple components work together (e.g., form submission with field validation).
- **End-to-End Tests**: Cypress scripts simulate user flows (login, add a sale, generate a report).
- **Linting & Formatting**: ESLint and Prettier enforce code style and catch common errors.
- **CI Pipeline**: Runs tests and linters on every pull request to catch issues early.

## 9. Conclusion and Overall Frontend Summary

TokoKiara’s frontend combines React, Inertia.js, MUI, and Tailwind CSS in a modern, component-driven architecture. Design principles like usability, accessibility, and responsiveness guide every choice, while tools like Vite and Dexie ensure fast, reliable performance—even offline. State is handled simply with Zustand, and robust testing keeps quality high. This setup supports easy scaling, quick feature development, and a smooth user experience, making TokoKiara a solid, maintainable platform for retail and inventory management.

*Unique aspects*: The use of Inertia.js avoids a separate API, Dexie enables offline resilience, and TinyMCE plus barcode generation cover rich content and physical-world integration—setting TokoKiara apart as a flexible, full-featured POS solution.
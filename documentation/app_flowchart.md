flowchart TD
    A[Start] --> B[Login Page]
    B --> C[Dashboard]
    C --> D[POS Module]
    C --> E[Inventory Module]
    C --> F[Sales Module]
    C --> G[Reporting Module]
    C --> H[Contacts Module]
    C --> I[Settings Module]
    C --> J[User Management]
    D --> K[Process Sale]
    K --> C
    E --> L[Manage Stock]
    L --> C
    F --> M[View Sales]
    M --> C
    G --> N[Generate Report]
    N --> C
    H --> O[Contact Management]
    O --> C
    I --> P[System Configuration]
    P --> C
    J --> Q[Manage Users]
    Q --> C
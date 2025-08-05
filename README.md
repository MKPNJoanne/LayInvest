# INNOVATIVE BUSINESS MODELING TOOL FOR SUSTAINABLE COMMERCIAL LAYER EGG PRODUCTION

**LayInvest** is a business forecasting and planning system developed as part of the final research project. It provides a simulation-based framework for supporting decision-making in Sri Lanka’s commercial layer poultry industry by integrating time-series forecasting (via Facebook Prophet) and deterministic modeling of production and costs.

---

## Project Overview

The Sri Lankan poultry industry is highly sensitive to fluctuations in market prices of key inputs such as feed, DOC (Day-Old Chicks), and egg selling prices. LayInvest offers a planning toolkit that helps farm operators, investors, and researchers simulate financial performance based on current and forecasted market conditions.

---

## Objectives

- Enable accurate price forecasting for market-sensitive variables
- Assist financial planning and risk evaluation for poultry operations
- Simulate egg production, mortality, and cost dynamics
- Help visualize potential profitability under different assumptions
- Use real-world farm and market data to drive practical insights

---

## Key Features

- Forecasting of:
  - Egg retail price trends
  - DOC price changes
  - Feed and cull bird price movements
- Simulation of:
  - Egg production and mortality rates (based on flock size)
  - Operational costs: labour, medicine, electricity, and maintenance
- Generation of:
  - Revenue projections
  - Cost breakdowns
  - Weekly/monthly profit estimates

---

## Forecasting Strategy

Only market-driven inputs are AI-predicted using **Facebook Prophet**. Other operational parameters follow historical or formula-based models.

| Variable              | Forecasted | Source             |
|-----------------------|------------|--------------------|
| Egg prices            | Yes        | Prophet Model      |
| Feed prices           | Yes        | Prophet Model      |
| DOC prices            | Yes        | Prophet Model      |
| Cull bird prices      | Yes        | Prophet Model      |
| Egg production yield  | No         | Formula-based      |
| Mortality rate        | No         | Linear (by flock)  |
| Operational costs     | No         | Static / adjustable|

---

## Technology Stack

| Component           | Description                            |
|---------------------|----------------------------------------|
| PHP 8.1+            | Core backend language                  |
| Yii2 Framework      | MVC structure and REST support         |
| PostgreSQL          | Relational database for data modeling  |
| Facebook Prophet    | Python library for time-series forecasts |
| Composer            | PHP package and dependency management  |


## License
This software is intended for research and academic use. For licensing in commercial or production environments, please contact the project owner.

## Contact
Project Author: MKPN Joanne
Student Number: 23084
Research Supervisor: Mr Gayan Perera

Institution: National School of Business Management

Email: pramudinurakshi27@gmail.com
© 2025 by MKPN Joanne 

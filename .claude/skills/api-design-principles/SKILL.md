---
name: api-design-principles
description: Master REST and GraphQL API design principles to build intuitive, scalable, and maintainable APIs that delight developers. Use when designing new APIs, reviewing API specifications, or establishing API design standards.
---

# API Design Principles

Master REST and GraphQL API design principles to build intuitive, scalable, and maintainable APIs that delight developers and stand the test of time.

## When to Use This Skill

- Designing new REST or GraphQL APIs
- Refactoring existing APIs for better usability
- Establishing API design standards for your team
- Reviewing API specifications before implementation
- Migrating between API paradigms (REST to GraphQL, etc.)
- Creating developer-friendly API documentation
- Optimizing APIs for specific use cases (mobile, third-party integrations)

## Core Concepts

### 1. RESTful Design Principles

**Resource-Oriented Architecture**
- Resources are nouns (users, orders, products), not verbs
- Use HTTP methods for actions (GET, POST, PUT, PATCH, DELETE)
- URLs represent resource hierarchies
- Consistent naming conventions

**HTTP Methods Semantics:**
- `GET`: Retrieve resources (idempotent, safe)
- `POST`: Create new resources
- `PUT`: Replace entire resource (idempotent)
- `PATCH`: Partial resource updates
- `DELETE`: Remove resources (idempotent)

### 2. GraphQL Design Principles

**Schema-First Development**
- Types define your domain model
- Queries for reading data
- Mutations for modifying data
- Subscriptions for real-time updates

**Query Structure:**
- Clients request exactly what they need
- Single endpoint, multiple operations
- Strongly typed schema
- Introspection built-in

### 3. API Versioning Strategies

**URL Versioning:**
```
/api/v1/users
/api/v2/users
```

**Header Versioning:**
```
Accept: application/vnd.api+json; version=1
```

**Query Parameter Versioning:**
```
/api/users?version=1
```

## REST API Design Patterns

### Pattern 1: Resource Collection Design

```
# Good: Resource-oriented endpoints
GET    /api/users              # List users (with pagination)
POST   /api/users              # Create user
GET    /api/users/{id}         # Get specific user
PUT    /api/users/{id}         # Replace user
PATCH  /api/users/{id}         # Update user fields
DELETE /api/users/{id}         # Delete user

# Nested resources
GET    /api/users/{id}/orders  # Get user's orders
POST   /api/users/{id}/orders  # Create order for user

# Bad: Action-oriented endpoints (avoid)
POST   /api/createUser
POST   /api/getUserById
POST   /api/deleteUser
```

### Pattern 2: Pagination and Filtering

```typescript
interface PaginationParams {
    page: number;        // Page number (1-indexed)
    page_size: number;   // Items per page (1-100)
}

interface FilterParams {
    status?: string;
    created_after?: string;
    search?: string;
}

interface PaginatedResponse<T> {
    items: T[];
    total: number;
    page: number;
    page_size: number;
    pages: number;
    has_next: boolean;
    has_prev: boolean;
}
```

### Pattern 3: Error Handling and Status Codes

```typescript
interface ErrorResponse {
    error: string;
    message: string;
    details?: object;
    timestamp: string;
    path: string;
}

// Consistent error responses
const STATUS_CODES = {
    success: 200,
    created: 201,
    no_content: 204,
    bad_request: 400,
    unauthorized: 401,
    forbidden: 403,
    not_found: 404,
    conflict: 409,
    unprocessable: 422,
    internal_error: 500
};
```

### Pattern 4: HATEOAS (Hypermedia as the Engine of Application State)

```typescript
interface UserResponse {
    id: string;
    name: string;
    email: string;
    _links: {
        self: { href: string };
        orders: { href: string };
        update: { href: string; method: string };
        delete: { href: string; method: string };
    };
}
```

## GraphQL Design Patterns

### Pattern 1: Schema Design

```graphql
# Clear type definitions
type User {
  id: ID!
  email: String!
  name: String!
  createdAt: DateTime!

  # Relationships
  orders(
    first: Int = 20
    after: String
    status: OrderStatus
  ): OrderConnection!

  profile: UserProfile
}

type Order {
  id: ID!
  status: OrderStatus!
  total: Money!
  items: [OrderItem!]!
  createdAt: DateTime!
  user: User!
}

# Pagination pattern (Relay-style)
type OrderConnection {
  edges: [OrderEdge!]!
  pageInfo: PageInfo!
  totalCount: Int!
}

type OrderEdge {
  node: Order!
  cursor: String!
}

type PageInfo {
  hasNextPage: Boolean!
  hasPreviousPage: Boolean!
  startCursor: String
  endCursor: String
}

# Enums for type safety
enum OrderStatus {
  PENDING
  CONFIRMED
  SHIPPED
  DELIVERED
  CANCELLED
}

# Query root
type Query {
  user(id: ID!): User
  users(first: Int = 20, after: String, search: String): UserConnection!
  order(id: ID!): Order
}

# Mutation root
type Mutation {
  createUser(input: CreateUserInput!): CreateUserPayload!
  updateUser(input: UpdateUserInput!): UpdateUserPayload!
  deleteUser(id: ID!): DeleteUserPayload!
}

# Input types for mutations
input CreateUserInput {
  email: String!
  name: String!
  password: String!
}

# Payload types for mutations
type CreateUserPayload {
  user: User
  errors: [Error!]
}
```

### Pattern 2: DataLoader (N+1 Problem Prevention)

```typescript
import { DataLoader } from 'dataloader';

class UserLoader extends DataLoader<string, User | null> {
    async batchLoadFn(userIds: string[]): Promise<(User | null)[]> {
        const users = await fetchUsersByIds(userIds);
        const userMap = new Map(users.map(user => [user.id, user]));
        return userIds.map(id => userMap.get(id) || null);
    }
}

class OrdersByUserLoader extends DataLoader<string, Order[]> {
    async batchLoadFn(userIds: string[]): Promise<Order[][]> {
        const orders = await fetchOrdersByUserIds(userIds);
        const ordersByUser = new Map<string, Order[]>();
        
        for (const order of orders) {
            const existing = ordersByUser.get(order.userId) || [];
            existing.push(order);
            ordersByUser.set(order.userId, existing);
        }
        
        return userIds.map(id => ordersByUser.get(id) || []);
    }
}
```

## Best Practices

### REST APIs
1. **Consistent Naming**: Use plural nouns for collections (`/users`, not `/user`)
2. **Stateless**: Each request contains all necessary information
3. **Use HTTP Status Codes Correctly**: 2xx success, 4xx client errors, 5xx server errors
4. **Version Your API**: Plan for breaking changes from day one
5. **Pagination**: Always paginate large collections
6. **Rate Limiting**: Protect your API with rate limits
7. **Documentation**: Use OpenAPI/Swagger for interactive docs

### GraphQL APIs
1. **Schema First**: Design schema before writing resolvers
2. **Avoid N+1**: Use DataLoaders for efficient data fetching
3. **Input Validation**: Validate at schema and resolver levels
4. **Error Handling**: Return structured errors in mutation payloads
5. **Pagination**: Use cursor-based pagination (Relay spec)
6. **Deprecation**: Use `@deprecated` directive for gradual migration
7. **Monitoring**: Track query complexity and execution time

## Common Pitfalls

- **Over-fetching/Under-fetching (REST)**: Fixed in GraphQL but requires DataLoaders
- **Breaking Changes**: Version APIs or use deprecation strategies
- **Inconsistent Error Formats**: Standardize error responses
- **Missing Rate Limits**: APIs without limits are vulnerable to abuse
- **Poor Documentation**: Undocumented APIs frustrate developers
- **Ignoring HTTP Semantics**: POST for idempotent operations breaks expectations
- **Tight Coupling**: API structure shouldn't mirror database schema

// Apollo Client Configuration for F.I.E.R.C.E Frontend
// This connects the frontend to the GraphQL API

const { ApolloClient, InMemoryCache, HttpLink } = window;

// Get or create Apollo Client instance
function createApolloClient() {
  const token = localStorage.getItem('authToken');

  return new ApolloClient({
    link: new HttpLink({
      uri: 'http://localhost:4000/graphql',
      credentials: 'include',
      headers: {
        ...(token && { Authorization: `Bearer ${token}` })
      }
    }),
    cache: new InMemoryCache()
  });
}

// Store token in localStorage
function setAuthToken(token) {
  localStorage.setItem('authToken', token);
}

// Get token from localStorage
function getAuthToken() {
  return localStorage.getItem('authToken');
}

// Clear token (logout)
function clearAuthToken() {
  localStorage.removeItem('authToken');
}

// GraphQL Query helper
async function graphqlQuery(query, variables = {}) {
  const token = getAuthToken();
  
  const response = await fetch('http://localhost:4000/graphql', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(token && { 'Authorization': `Bearer ${token}` })
    },
    body: JSON.stringify({ query, variables })
  });

  const result = await response.json();
  
  if (result.errors) {
    throw new Error(result.errors[0].message);
  }

  return result.data;
}

// GraphQL Mutation helper
async function graphqlMutation(mutation, variables = {}) {
  return graphqlQuery(mutation, variables);
}

// Export functions
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    createApolloClient,
    setAuthToken,
    getAuthToken,
    clearAuthToken,
    graphqlQuery,
    graphqlMutation
  };
}

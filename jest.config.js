module.exports = {
  testEnvironment: 'jsdom',
  moduleNameMapper: {
    // Mock pour les fichiers CSS importés
    '\\.(css|less|scss|sass)$': '<rootDir>/tests/frontend/mocks/styleMock.js',
    // Mock pour les fichiers d'images
    '\\.(jpg|jpeg|png|gif|webp|svg)$': '<rootDir>/tests/frontend/mocks/fileMock.js'
  },
  setupFilesAfterEnv: ['<rootDir>/tests/frontend/setup.js'],
  testMatch: [
    '**/tests/frontend/**/*.test.js'
  ],
  collectCoverage: true,
  collectCoverageFrom: [
    'src/js/**/*.js'
  ],
  coverageDirectory: 'coverage',
  transform: {
    // Utiliser babel pour transpiler les fichiers JS
    '^.+\\.jsx?$': 'babel-jest'
  }
};

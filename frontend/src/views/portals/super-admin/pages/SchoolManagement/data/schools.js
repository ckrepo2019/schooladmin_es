import { faker } from '@faker-js/faker'

const schoolTypes = ['public', 'private', 'charter']
const statuses = ['active', 'inactive', 'pending']

const schoolPrefixes = [
  'Lincoln',
  'Washington',
  'Jefferson',
  'Roosevelt',
  'Kennedy',
  'Madison',
  'Franklin',
  'Adams',
  'Jackson',
  'Wilson',
  'Grant',
  'Monroe',
  'Harrison',
  'Central',
  'North',
  'South',
  'East',
  'West',
  'Highland',
  'Valley',
  'Mountain',
  'River',
  'Lake',
  'Pine',
  'Oak',
  'Maple',
  'Cedar',
  'Hillcrest',
  'Parkview',
  'Riverside',
]

const schoolSuffixes = [
  'Elementary School',
  'Middle School',
  'High School',
  'Academy',
  'Preparatory School',
  'International School',
  'Charter School',
]

// Generate a unique school name
function generateSchoolName(index) {
  const prefix = schoolPrefixes[index % schoolPrefixes.length]
  const suffix = schoolSuffixes[Math.floor(index / schoolPrefixes.length) % schoolSuffixes.length]
  return `${prefix} ${suffix}`
}

// Generate a school code
function generateSchoolCode(index) {
  const prefix = String(index + 1).padStart(3, '0')
  return `SCH-${prefix}`
}

// Create fake schools data
export function createSchools(count = 50) {
  // Use a seed for consistent data generation
  faker.seed(123)

  return Array.from({ length: count }, (_, index) => {
    const createdAt = faker.date.past({ years: 5 })
    const updatedAt = faker.date.between({ from: createdAt, to: new Date() })

    return {
      id: faker.string.uuid(),
      schoolName: generateSchoolName(index),
      schoolCode: generateSchoolCode(index),
      schoolType: faker.helpers.arrayElement(schoolTypes),
      status: faker.helpers.arrayElement(statuses),
      address: faker.location.streetAddress({ useFullAddress: true }),
      contactEmail: faker.internet.email().toLowerCase(),
      contactPhone: faker.phone.number('###-###-####'),
      principalName: faker.person.fullName(),
      totalStudents: faker.number.int({ min: 50, max: 2000 }),
      totalTeachers: faker.number.int({ min: 10, max: 150 }),
      createdAt,
      updatedAt,
    }
  })
}

export const schools = createSchools()

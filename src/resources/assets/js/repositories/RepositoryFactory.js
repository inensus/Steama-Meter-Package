import PaginatorRepository from './PaginatorRepository'
import CredentialRepository from './CredentialRepository'
import SiteRepository from './SiteRepository'
import CustomerRepository from './CustomerRepository'
import MeterRepository from './MeterRepository'
import AgentRepository from './AgentRepository'
const repositories = {
    'paginate': PaginatorRepository,
    'credential':CredentialRepository,
    'site':SiteRepository,
    'customer':CustomerRepository,
    'meter':MeterRepository,
    'agent':AgentRepository
}
export default {
    get: name => repositories[name]
}
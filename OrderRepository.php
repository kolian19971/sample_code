<?php

namespace BillingBundle\Repository;

use BillingBundle\Entity\Order;
use BillingBundle\Entity\Payment;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use UserBundle\Entity\User;
use Doctrine\ORM\Query;
use \Doctrine\ORM\QueryBuilder;

class OrderRepository extends EntityRepository
{
    /**
     * Get base QueryBuilder for orders
     *
     * @param array $params
     * @return QueryBuilder
     */
    protected function getBaseQueryForOrder(array $params = []): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('o')
                             ->select('o, a, u, m, t, p')
                             ->leftJoin('o.account', 'a');

        if(!isset($params['remove_payments']))
            $queryBuilder->leftJoin('o.payments', 'p');

        $queryBuilder->leftJoin('a.user', 'u')
                    ->innerJoin('o.material', 'm');

        return $queryBuilder;
    }

    /**
     * Get query order by params
     *
     * @param array $params
     * @param array|string[] $orderBy
     * @return QueryBuilder
     * @throws \Exception
     */
    public function getQueryForOrders(array $params = [], array $orderBy = [
        'column' => 'o.id',
        'value' => 'DESC'
    ] ): QueryBuilder{

        $q = $this->getBaseQueryForOrder($params);

        if(isset($params['lang']) && !empty($params['lang'])){
            $q->innerJoin('m.translates', 't', Join::WITH, 't.material=m.id AND t.lang=:lang')
              ->setParameter('lang', $params['lang']);
        }

        $q->where('u.isActive=:isActive')
                  ->setParameter('isActive', true);

        if ( isset($params['query']) && !empty($params['query']) )
            $q->andWhere('u.name LIKE :query OR u.surname LIKE :query OR u.patronymic LIKE :query OR u.email LIKE :query OR u.phone LIKE :query OR p.paymentId LIKE :query')
              ->setParameter('query', "%{$params['query']}%");

        if ( isset($params['isGranted']) && $params['isGranted'] === true && isset($params['currentUser']) && $params['currentUser'] instanceof User )
            $q->andWhere('o.createdBy=:createdBy')
              ->setParameter('createdBy', $params['currentUser']);

        if ( isset($params['status']) && !empty($params['status']) )
            $q->andWhere('o.status = :status')
              ->setParameter('status', $params['status']);

        if ( isset($params['creatByIds'][0]) )
            $q->andWhere('o.createdBy IN(:createByArray)')
              ->setParameter('createByArray', $params['creatByIds']);

        if( isset($params['dateCreate']) && !empty($params['dateCreate']) && isset($params['getDatetimeFromStr']) && isset($params['dateFormat']) ){

            $dateArray = explode('-', $params['dateCreate']);

            $dateCreateFrom = $params['getDatetimeFromStr']($dateArray[0] ?? null, $params['dateFormat']);
            $dateCreateTo = $params['getDatetimeFromStr']($dateArray[1] ?? null, $params['dateFormat']);

            if($dateCreateFrom instanceof \DateTime){
                $q->andWhere('o.createdAt >= :dateCreateFrom')
                  ->setParameter('dateCreateFrom', $dateCreateFrom);
            }

            if($dateCreateTo instanceof \DateTime){
                $q->andWhere('o.createdAt <= :dateCreateTo')
                  ->setParameter('dateCreateTo', $dateCreateTo);
            }
        }

        if( isset($params['datePay']) && !empty($params['datePay']) && isset($params['getDatetimeFromStr']) && isset($params['dateFormat']) ){
            $dateArray = explode('-', $params['datePay']);

            $datePayFrom = $params['getDatetimeFromStr']($dateArray[0] ?? null, $params['dateFormat']);
            $datePayTo = $params['getDatetimeFromStr']($dateArray[1] ?? null, $params['dateFormat']);

            if($datePayFrom instanceof \DateTime){
                $q->andWhere('p.updatedAt >= :datePayFrom')
                  ->setParameter('datePayFrom', $datePayFrom);
            }

            if($datePayTo instanceof \DateTime){
                $q->andWhere('p.updatedAt <= :datePayTo')
                  ->setParameter('datePayTo', $datePayTo);
            }
        }

        return $q->orderBy($orderBy['column'], $orderBy['value']);
    }

    /**
     * Get query users by params
     *
     * @param array $params
     * @return Query
     */
    public function getQueryForCreatedByParams(array $params = [] ): Query{
        $userIdsSql = "SELECT IDENTITY(o.createdBy) FROM BillingBundle:Order o WHERE IDENTITY(o.createdBy) IS NOT NULL";

        if(isset($params['createByIds']) && isset($params['createByIds'][0]))
            $userIdsSql = "'".implode("', '", $params['createByIds'])."'";

        $querySql = "SELECT u.id, u.name, u.surname FROM UserBundle:User u WHERE u.id IN ($userIdsSql)";

        if(isset($params['query']) && !empty($params['query']))
            $querySql .= " AND (u.name LIKE '%".$params['query']."%' or u.surname LIKE '%".$params['query']."%' )";

        return  $this->getEntityManager()
                     ->createQuery($querySql);
    }

    /**
     * Get total order count by params
     *
     * @param array $params
     * @return int|mixed|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function getTotalOrderCountByParams( array $params = [] ){
        return $this->getQueryForOrders($params)
                    ->select('COUNT(DISTINCT o.id) orderCount')
                    ->getQuery()
                    ->getSingleScalarResult();
    }

    /**
     * Get total full paid count by params
     *
     * @param array $params
     * @return int|mixed|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTotalOrderFullPaidCountByParams( array $params = [] ){
        return $this->getQueryForOrders($params)
                    ->andWhere('o.status= :orderFullPaidStatus')
                    ->setParameter('orderFullPaidStatus', Order::STATUS_PAID_FOR)
                    ->select('COUNT(DISTINCT o.id) orderCount')
                    ->getQuery()
                    ->getSingleScalarResult();
    }


    /**
     * Get total partial paid count by params
     *
     * @param array $params
     * @return int|mixed|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTotalOrderPartialPaidCountByParams( array $params = [] ){
        return $this->getQueryForOrders($params)
                    ->andWhere('o.status= :orderFullPaidStatus')
                    ->setParameter('orderFullPaidStatus', Order::STATUS_PARTIALLY_PAID)
                    ->select('COUNT(DISTINCT o.id) orderCount')
                    ->getQuery()
                    ->getSingleScalarResult();
    }

    /**
     * Get unique order ids array by params
     *
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function getUniqueOrderIdsArrayByParams(array $params = []): array{

        $singleResult = $this->getQueryForOrders($params)
                             ->select('DISTINCT o.id')
                             ->getQuery()
                             ->getArrayResult();

        return array_map(function ($orderItem){
            return $orderItem['id'];
        }, $singleResult);
    }

    /**
     * Get total order payments count by params
     *
     * @param array $params
     * @return int|mixed|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function getTotalPaymentsCountByParams( array $params = [] ){
        return $this->getQueryForOrders($params)
                    ->select('COUNT(p) allPaymentsCount')
                    ->getQuery()
                    ->getSingleScalarResult();
    }

    /**
     * Get total order payments count by params with created status
     *
     * @param array $params
     * @return int|mixed|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function getTotalPaymentsCreatedCountByParams( array $params = [] ){
        return $this->getQueryForOrders($params)
                    ->andWhere('p.status = :createStatus')
                    ->setParameter('createStatus', Payment::STATUS_CREATE)
                    ->select('COUNT(p) allPaymentsCount')
                    ->getQuery()
                    ->getSingleScalarResult();
    }

    /**
     * Get total order payments count by params with paid status
     *
     * @param array $params
     * @return int|mixed|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function getTotalPaymentsPaidCountByParams( array $params = [] ){
        return $this->getQueryForOrders($params)->andWhere('p.status = :payStatus')
                    ->setParameter('payStatus', Payment::STATUS_PAY)
                    ->select('COUNT(p) allPaymentsCount')
                    ->getQuery()
                    ->getSingleScalarResult();
    }

    /**
     * Get total order partial count by params
     *
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public function getTotalOrderPartialCountByParams( array $params = [] ){

        $resultArray = $this->getQueryForOrders($params)
            ->select('COUNT(p.id) pCount, o.id oid, p.status pStatus, o.status orderStatus')

            ->having('pCount > :minPaymentCount')
            ->setParameter('minPaymentCount', 1)

            ->orHaving('pStatus <> :paymentPaidStatus')
            ->setParameter('paymentPaidStatus', Payment::STATUS_PAY)

            ->orHaving('orderStatus = :orderStatus')
            ->setParameter('orderStatus', Order::STATUS_PARTIALLY_PAID)

            ->groupBy('o.id')
            ->getQuery()
            ->getArrayResult();

        return count($resultArray);
    }


}
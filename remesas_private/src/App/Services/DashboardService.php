<?php
namespace App\Services;

use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\RateRepository;
use App\Repositories\EstadoTransaccionRepository;
use App\Repositories\CountryRepository;
use App\Repositories\TasasHistoricoRepository;
use Exception;

class DashboardService
{
    private TransactionRepository $transactionRepository;
    private UserRepository $userRepository;
    private RateRepository $rateRepository;
    private EstadoTransaccionRepository $estadoTxRepo;
    private CountryRepository $countryRepository;
    private TasasHistoricoRepository $tasasHistoricoRepo;

    private const ESTADO_EN_VERIFICACION_ID = 3;
    private const ESTADO_EN_PROCESO = 'En Proceso';
    private const ESTADO_PAGADO = 'Exitoso';
    private const ESTADO_PENDIENTE = 'Pendiente de Pago';

    public function __construct(
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        RateRepository $rateRepository,
        EstadoTransaccionRepository $estadoTxRepo,
        CountryRepository $countryRepository,
        TasasHistoricoRepository $tasasHistoricoRepo
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->userRepository = $userRepository;
        $this->rateRepository = $rateRepository;
        $this->estadoTxRepo = $estadoTxRepo;
        $this->countryRepository = $countryRepository;
        $this->tasasHistoricoRepo = $tasasHistoricoRepo;
    }

    private function getEstadoId(string $nombreEstado): int
    {
        $id = $this->estadoTxRepo->findIdByName($nombreEstado);
        if ($id === null) {
            throw new Exception("Configuración interna: Estado de transacción '{$nombreEstado}' no encontrado.", 500);
        }
        return $id;
    }

    public function getAdminDashboardStats(): array
    {
        $totalUsers = $this->userRepository->countAll();
        $estadoVerificacionID = self::ESTADO_EN_VERIFICACION_ID;
        $estadoEnProcesoID = $this->getEstadoId(self::ESTADO_EN_PROCESO);
        $estadoPendienteID = $this->getEstadoId(self::ESTADO_PENDIENTE);

        $pendingTransactions = $this->transactionRepository->countByStatus([
            $estadoVerificacionID,
            $estadoEnProcesoID,
            $estadoPendienteID
        ]);

        $topDestino = $this->transactionRepository->getTopCountries('Destino', 5);
        $topOrigen = $this->transactionRepository->getTopCountries('Origen', 5);
        $txStats = $this->transactionRepository->getTransactionStats();
        $topUsers = $this->transactionRepository->getTopUsers(5);

        return [
            'kpis' => [
                'totalUsers' => $totalUsers,
                'pendingTransactions' => $pendingTransactions,
                'averageDaily' => (float) number_format($txStats['PromedioDiario'], 2),
                'busiestMonth' => $txStats['MesMasConcurrido'] . ' (' . $txStats['TotalMesMasConcurrido'] . ' trans.)'
            ],
            'charts' => [
                'topDestino' => ['labels' => array_column($topDestino, 'NombrePais'), 'data' => array_column($topDestino, 'Total')],
                'topOrigen' => ['labels' => array_column($topOrigen, 'NombrePais'), 'data' => array_column($topOrigen, 'Total')]
            ],
            'tables' => ['topUsers' => $topUsers]
        ];
    }

    public function getDolarBcvData(int $origenId, int $destinoId, int $days = 30): array
    {
        $history = $this->tasasHistoricoRepo->getRateHistoryByDate($origenId, $destinoId, $days);
        $currentRef = $this->rateRepository->findReferentialRate($origenId, $destinoId);
        $valorActual = (float) ($currentRef['ValorTasa'] ?? 0);

        $labels = [];
        $dataPoints = [];

        $startDate = new \DateTime("-{$days} days");
        $endDate = new \DateTime();
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, $endDate);

        $historyMap = [];
        foreach ($history as $h) {
            $historyMap[$h['Fecha']] = (float) $h['TasaPromedio'];
        }

        $lastValue = !empty($historyMap) ? reset($historyMap) : $valorActual;

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $displayDate = $date->format('d/m');

            if (isset($historyMap[$dateStr])) {
                $lastValue = $historyMap[$dateStr];
            }

            $labels[] = $displayDate;
            $dataPoints[] = $lastValue;
        }

        $todayLabel = date("d/m");
        if (end($labels) !== $todayLabel) {
            $labels[] = $todayLabel;
            $dataPoints[] = $valorActual;
        } else {
            $dataPoints[count($dataPoints) - 1] = $valorActual;
        }

        $dec = ($valorActual < 1000) ? 4 : 2;
        $textoTasaHoy = number_format($valorActual, $dec, ',', '.');

        return [
            'success' => true,
            'valorActual' => $valorActual,
            'textoTasa' => $textoTasaHoy,
            'monedaOrigen' => $this->countryRepository->findMonedaById($origenId) ?? 'N/A',
            'monedaDestino' => $this->countryRepository->findMonedaById($destinoId) ?? 'N/A',
            'labels' => $labels,
            'data' => $dataPoints,
            'lastUpdate' => date('Y-m-d H:i:s')
        ];
    }
}